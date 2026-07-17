<?php

declare(strict_types=1);

use orange\framework\Event;
use orange\framework\exceptions\InvalidValue;

final class EventTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = Event::getInstance([]);
    }

    // Tests
    public function testRegisterClosure(): void
    {
        $this->instance->register('open.file', function (&$payload) {
            $payload[] = 'open.file normal 1';
        });

        $this->assertContains('open.file', $this->instance->triggers());
        $this->assertTrue($this->instance->has('open.file'));
    }

    public function testRegisterClassMethod(): void
    {
        $this->instance->register('close.file', ['class', 'method']);

        $this->assertContains('close.file', $this->instance->triggers());
        $this->assertTrue($this->instance->has('close.file'));
    }

    public function testRegisterEventUnknownCallableException(): void
    {
        $this->expectException(TypeError::class);

        $this->assertNull($this->instance->register('open.file', 123));
    }

    public function testRegisterEventArrayCountException(): void
    {
        $this->expectException(InvalidValue::class);

        $this->assertNull($this->instance->register('open.file', ['foo']));
    }

    public function testRegisterEventCalledArrayException(): void
    {
        $this->expectException(InvalidValue::class);

        $this->assertNull($this->instance->register('open.file', ['controller', 'method', 'extra!']));
    }

    public function testRegisterMultiple(): void
    {
        $this->instance->registerMultiple([
            'open.file' => function (&$payload) {
                $payload[] = 'open.file normal 2';
            },
            'close.file' => function ($payload) {
                $payload[] = 'open.file normal 3';
            }
        ]);

        $this->assertContains('open.file', $this->instance->triggers());
        $this->assertTrue($this->instance->has('open.file'));
        $this->assertContains('close.file', $this->instance->triggers());
        $this->assertTrue($this->instance->has('close.file'));
        $this->assertFalse($this->instance->has('dance.file'));
    }

    public function testTrigger(): void
    {
        $this->instance->register('open.file', function (&$payload) {
            $payload[] = 'open.file PRIORITY_NORMAL';
        }, Event::PRIORITY_NORMAL);

        $this->instance->register('open.file', function (&$payload) {
            $payload[] = 'open.file PRIORITY_LOW';
        }, Event::PRIORITY_LOW);

        $this->instance->register('open.file', function (&$payload) {
            $payload[] = 'open.file PRIORITY_HIGH';
        }, Event::PRIORITY_HIGH);

        $this->instance->register('open.file', function (&$payload) {
            $payload[] = 'open.file PRIORITY_HIGHEST';
        }, Event::PRIORITY_HIGHEST);

        $this->instance->register('open.file', function (&$payload) {
            $payload[] = 'open.file PRIORITY_LOWEST';
        }, Event::PRIORITY_LOWEST);

        $payload = [];

        $this->instance->trigger('open.file', $payload);

        $matches[] = 'open.file PRIORITY_HIGHEST';
        $matches[] = 'open.file PRIORITY_HIGH';
        $matches[] = 'open.file PRIORITY_NORMAL';
        $matches[] = 'open.file PRIORITY_LOW';
        $matches[] = 'open.file PRIORITY_LOWEST';

        $this->assertEquals($matches, $payload);
    }

    public function testTriggerClassMethod(): void
    {
        include WORKINGDIR . '/dummyEvent.php';

        $this->instance->register('tester', ['EventClassName', 'EventMethodName']);

        $arg1 = '';
        $arg2 = 'Johnny Appleseed';

        $this->instance->trigger('tester', $arg1, $arg2);

        $this->assertEquals('[Johnny Appleseed]', $arg1);
    }

    public function testUnregister(): void
    {
        $eventId = $this->instance->register('open.file', function (&$payload) {
            $payload[] = 'open.file normal 1';
        });

        $this->assertContains('open.file', $this->instance->triggers());
        $this->assertTrue($this->instance->has('open.file'));

        $this->assertTrue($this->instance->unregister($eventId));

        $this->assertFalse($this->instance->has('open.file'));
    }

    public function testUnregisterAll(): void
    {
        $this->instance->register('open.file', function (&$payload) {
            $payload[] = 'open.file normal 1';
        });

        $this->assertContains('open.file', $this->instance->triggers());
        $this->assertTrue($this->instance->has('open.file'));

        $this->instance->unregisterAll('open.file');

        $this->assertFalse($this->instance->has('open.file'));
    }

    public function testConstructorRegistersConfiguredEvents(): void
    {
        $event = Event::newInstance([
            'boot.event' => [
                [function (&$payload) {
                    $payload[] = 'configured';
                }, Event::PRIORITY_NORMAL],
            ],
        ]);

        $this->assertTrue($event->has('boot.event'));
    }

    public function testTriggerStopsWhenListenerReturnsFalse(): void
    {
        $this->instance->register('chain', function (&$payload) {
            $payload[] = 'first';
            return false; // halt propagation
        }, Event::PRIORITY_HIGH);

        $this->instance->register('chain', function (&$payload) {
            $payload[] = 'second';
        }, Event::PRIORITY_LOW);

        $payload = [];
        $this->instance->trigger('chain', $payload);

        $this->assertEquals(['first'], $payload);
    }

    public function testUnregisterReturnsFalseForUnknownId(): void
    {
        $this->assertFalse($this->instance->unregister(999999));
    }

    public function testUnregisterAllWithoutTriggerClearsEverything(): void
    {
        $this->instance->register('a.event', fn(&$p) => null);
        $this->instance->register('b.event', fn(&$p) => null);

        $this->assertTrue($this->instance->unregisterAll());
        $this->assertEquals([], $this->instance->triggers());
    }

    public function testUnregisterAllUnknownTriggerReturnsFalse(): void
    {
        $this->assertFalse($this->instance->unregisterAll('never.registered'));
    }

    public function testDisableStopsTriggersAndEnableResumesThem(): void
    {
        $this->instance->register('some.event', function (&$payload) {
            $payload[] = 'fired';
        });

        $payload = [];

        // disabled: the listener must not run
        $this->instance->disable();
        $this->instance->trigger('some.event', $payload);
        $this->assertEquals([], $payload);

        // re-enabled: the listener runs again
        $this->instance->enable();
        $this->instance->trigger('some.event', $payload);
        $this->assertEquals(['fired'], $payload);
    }
}
