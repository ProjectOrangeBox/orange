<?php

declare(strict_types=1);

use orange\framework\Container;
use orange\framework\interfaces\ContainerInterface;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\container\FailedToAutoWire;

/**
 * Covers Container features beyond the basic get/set exercised in ContainerTest:
 * autowiring, alias resolution/loops, singleton promotion, and introspection.
 */
final class ContainerAdvancedTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        require_once MOCKDIR . '/containerMocks.php';
        require_once MOCKDIR . '/theSameAutomobile.php';

        $this->instance = Container::getInstance([
            'foo' => 'bar',
        ]);
    }

    /* self registration */

    public function testContainerRegistersItself(): void
    {
        $this->assertSame($this->instance, $this->instance->get('container'));
    }

    /* introspection */

    public function testGetServicesListsRegisteredNames(): void
    {
        $names = $this->instance->getServices();

        $this->assertContains('foo', $names);
        $this->assertContains('container', $names);
    }

    public function testDebugInfoReportsServiceTypes(): void
    {
        $this->instance->set('aClosure', fn($c) => new stdClass());
        $this->instance->set('anObject', new stdClass());

        $debug = $this->instance->debugInfo();

        $this->assertEquals('string', $debug['foo']);       // a scalar VALUE
        $this->assertEquals('closure', $debug['aclosure']);
        $this->assertEquals('object', $debug['anobject']);
        $this->assertEquals('object', $debug['container']);
    }

    public function testMagicDebugInfoMatchesDebugInfo(): void
    {
        $this->assertEquals($this->instance->debugInfo(), $this->instance->__debugInfo());
    }

    /* aliases */

    public function testAliasResolvesToTarget(): void
    {
        $this->instance->set('@nickname', 'foo');

        $this->assertEquals('bar', $this->instance->get('nickname'));
    }

    public function testAliasChainResolves(): void
    {
        $this->instance->set('@a', 'foo');
        $this->instance->set('@b', 'a');

        $this->assertEquals('bar', $this->instance->get('b'));
    }

    public function testAliasLoopThrowsInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Alias resolution exceeded maximum depth');

        // a -> b -> a -> ... never resolves to a real service
        $this->instance->set('@a', 'b');
        $this->instance->set('@b', 'a');

        $this->instance->get('a');
    }

    /* closures & singleton promotion */

    public function testClosureReturningNonSingletonIsNotPromoted(): void
    {
        $this->instance->set('fresh', fn($c) => new stdClass());

        $first = $this->instance->get('fresh');
        $second = $this->instance->get('fresh');

        // still a closure, so each call builds a new object
        $this->assertNotSame($first, $second);
        $this->assertEquals('closure', $this->instance->debugInfo()['fresh']);
    }

    public function testClosureReturningSingletonIsPromoted(): void
    {
        $this->instance->set('sng', fn($c) => theSameAutomobile::getInstance());

        $first = $this->instance->get('sng');

        // after first resolution it is converted to a stored object value
        $this->assertEquals('object', $this->instance->debugInfo()['sng']);

        $second = $this->instance->get('sng');
        $this->assertSame($first, $second);
    }

    /* autowiring */

    public function testAutoWireViaPublicConstructor(): void
    {
        $this->instance->set('^service', autowireConstructorMock::class);

        $service = $this->instance->get('service');

        $this->assertInstanceOf(autowireConstructorMock::class, $service);
        // the #[AutoWire('foo')] on the constructor injected the 'foo' service value
        $this->assertEquals('bar', $service->injected);
    }

    public function testAutoWireViaGetInstance(): void
    {
        $this->instance->set('^service', autowireGetInstanceMock::class);

        $service = $this->instance->get('service');

        $this->assertInstanceOf(autowireGetInstanceMock::class, $service);
        $this->assertEquals('bar', $service->injected);
    }

    public function testAutoWirePlainClassWithNoAttributes(): void
    {
        $this->instance->set('^plain', autowirePlainMock::class);

        $service = $this->instance->get('plain');

        $this->assertInstanceOf(autowirePlainMock::class, $service);
        $this->assertTrue($service->built);
    }

    public function testAutoWireImpossibleClassThrows(): void
    {
        $this->expectException(FailedToAutoWire::class);

        $this->instance->set('^broken', autowireImpossibleMock::class);
        $this->instance->get('broken');
    }

    public function testAutoWireClassWithoutExplicitConstructorThrows(): void
    {
        // documents a limitation: autoWire needs an explicit __construct or a
        // getInstance method; a class relying on the implicit default
        // constructor (e.g. stdClass) cannot be autowired.
        $this->expectException(FailedToAutoWire::class);

        $this->instance->set('^plainObject', stdClass::class);
        $this->instance->get('plainObject');
    }

    public function testUnknownServiceTypeThrows(): void
    {
        // force an invalid registered service type via reflection
        $registered = $this->getPrivatePublic('registeredServices');
        $registered['weird'] = [ContainerInterface::TYPE => 999, ContainerInterface::REFERENCE => 'x'];
        $this->setPrivatePublic('registeredServices', $registered);

        $this->expectException(\orange\framework\exceptions\container\ServiceNotFound::class);
        $this->instance->get('weird');
    }
}
