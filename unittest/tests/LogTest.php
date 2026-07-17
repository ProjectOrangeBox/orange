<?php

declare(strict_types=1);

use Psr\Log\NullLogger;
use orange\framework\Log;
use orange\framework\exceptions\IncorrectInterface;

final class LogTest extends UnitTestHelper
{
    protected $instance;
    protected $config = [
        'filepath' => WORKINGDIR . '/log.txt',
        'threshold' => 128,
        'permissions' => 0777,
    ];

    protected function setUp(): void
    {
        if (file_exists($this->config['filepath'])) {
            unlink($this->config['filepath']);
        }

        $this->instance = Log::getInstance($this->config);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->config['filepath'])) {
            unlink($this->config['filepath']);
        }
    }

    // Tests
    public function testChangeThreshold(): void
    {
        $this->instance->changeThreshold(0);

        $this->assertEquals(0, $this->instance->getThreshold());

        $this->instance->changeThreshold(255);

        $this->assertEquals(255, $this->instance->getThreshold());
    }

    public function testGetThreshold(): void
    {
        $this->instance->changeThreshold(255);

        $this->assertEquals(255, $this->instance->getThreshold());
    }

    public function testIsEnabled(): void
    {
        $this->instance->changeThreshold(0);

        $this->assertFalse($this->instance->isEnabled());

        $this->instance->changeThreshold(255);

        $this->assertTrue($this->instance->isEnabled());
    }

    public function test__call(): void
    {
        $this->instance->changeThreshold(255);

        $this->instance->emergency('This is an emergency');
        $this->instance->notice('This is an notice 111');

        $this->assertFileExists($this->config['filepath']);

        $this->assertStringContainsString('This is an emergency', file_get_contents($this->config['filepath']));
        $this->assertStringContainsString('This is an notice 111', file_get_contents($this->config['filepath']));

        // disable notice
        $this->instance->changeThreshold(223);

        $this->instance->notice('This is an notice 222');

        $this->assertStringNotContainsString('This is an notice 222', file_get_contents($this->config['filepath']));
    }

    public function testAllPsr3LevelMethodsWrite(): void
    {
        $this->instance->changeThreshold(255);

        $this->instance->emergency('lvl-emergency');
        $this->instance->alert('lvl-alert');
        $this->instance->critical('lvl-critical');
        $this->instance->error('lvl-error');
        $this->instance->warning('lvl-warning');
        $this->instance->notice('lvl-notice');
        $this->instance->info('lvl-info');
        $this->instance->debug('lvl-debug');

        $contents = file_get_contents($this->config['filepath']);

        foreach (['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'] as $lvl) {
            $this->assertStringContainsString('lvl-' . $lvl, $contents);
        }
    }

    public function testLogWritesContext(): void
    {
        $this->instance->changeThreshold(255);

        $this->instance->error('with context', ['user' => 'johnny', 'id' => 42]);

        $contents = file_get_contents($this->config['filepath']);

        $this->assertStringContainsString('with context', $contents);
        $this->assertStringContainsString('johnny', $contents);
    }

    public function testWriteWithStringLevel(): void
    {
        $this->instance->changeThreshold(255);

        $this->instance->write('error', 'string level message');

        $this->assertStringContainsString('string level message', file_get_contents($this->config['filepath']));
    }

    public function testDisabledLevelIsNotWritten(): void
    {
        // threshold 0 disables logging entirely
        $this->instance->changeThreshold(0);

        $this->instance->emergency('should not appear');

        $this->assertFileDoesNotExist($this->config['filepath']);
    }

    public function testCustomHandlerDoesNotRequireWritableFilepath(): void
    {
        // an unwritable/nonexistent filepath must not matter when a custom PSR-3
        // handler is configured - changeThreshold() used to validate it unconditionally
        $config = $this->config;
        $config['handler'] = new NullLogger();
        $config['filepath'] = '/this/path/does/not/exist/and/is/not/creatable/log.txt';

        $instance = Log::newInstance($config);
        $instance->changeThreshold(255);

        $instance->emergency('should not throw');

        $this->assertFileDoesNotExist($config['filepath']);
    }

    public function testMonoLoggerException(): void
    {
        $this->expectException(IncorrectInterface::class);

        $this->config['handler'] = new stdClass();

        Log::newInstance($this->config);
    }
}
