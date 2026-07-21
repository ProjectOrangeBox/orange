<?php

declare(strict_types=1);

use Psr\Log\NullLogger;
use orange\framework\Log;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\IncorrectInterface;
use orange\framework\exceptions\filesystem\DirectoryNotWritable;

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

    public function testIsLevelEnabledIsMemoizedPerLevel(): void
    {
        $this->instance->changeThreshold(LOG::ALERT | LOG::DEBUG);

        // repeated calls for the same level must keep returning the same
        // (correct) answer, whether served from the cache or computed fresh
        $this->assertTrue($this->instance->isLevelEnabled(LOG::ALERT));
        $this->assertTrue($this->instance->isLevelEnabled(LOG::ALERT));
        $this->assertFalse($this->instance->isLevelEnabled(LOG::ERROR));
        $this->assertFalse($this->instance->isLevelEnabled(LOG::ERROR));

        // string and int forms of the same level must hit the same cache entry
        $this->assertTrue($this->instance->isLevelEnabled('DEBUG'));
        $this->assertTrue($this->instance->isLevelEnabled(LOG::DEBUG));
    }

    public function testIsLevelEnabledCacheIsInvalidatedByChangeThreshold(): void
    {
        $this->instance->changeThreshold(0);

        $this->assertFalse($this->instance->isLevelEnabled(LOG::ERROR));

        // must not keep returning the memoized `false` from before the threshold changed
        $this->instance->changeThreshold(LOG::ERROR);

        $this->assertTrue($this->instance->isLevelEnabled(LOG::ERROR));

        // and the reverse: a memoized `true` must not survive being disabled again
        $this->instance->changeThreshold(0);

        $this->assertFalse($this->instance->isLevelEnabled(LOG::ERROR));
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

    public function testConstructorThrowsWhenHandlerIsNotObject(): void
    {
        $this->expectException(InvalidValue::class);

        $config = $this->config;
        $config['handler'] = 'not-an-object';

        Log::newInstance($config);
    }

    public function testConvert2ThrowsForUnknownStringLevel(): void
    {
        $this->expectException(InvalidValue::class);

        $this->callMethod('convert2', ['bogus', 'string']);
    }

    public function testConvert2ThrowsForUnknownIntLevel(): void
    {
        $this->expectException(InvalidValue::class);

        $this->callMethod('convert2', [999999, 'int']);
    }

    public function testIsFileWritableCreatesMissingDirectory(): void
    {
        $newDir = WORKINGDIR . '/logdircreated';

        if (is_dir($newDir)) {
            rmdir($newDir);
        }

        try {
            $this->assertTrue($this->callMethod('isFileWritable', [$newDir . '/log.txt']));
            $this->assertDirectoryExists($newDir);
        } finally {
            if (is_dir($newDir)) {
                rmdir($newDir);
            }
        }
    }

    public function testIsFileWritableThrowsWhenDirectoryCreationFails(): void
    {
        // point the "directory" at a path that can never be created because a
        // regular file already occupies that path segment, forcing mkdir() to
        // fail; a temporary error handler upgrades the resulting PHP warning to
        // a Throwable so isFileWritable()'s try/catch around mkdir() is exercised
        $blockerFile = WORKINGDIR . '/logblocker';
        file_put_contents($blockerFile, '');

        set_error_handler(function ($errno, $errstr) {
            throw new \ErrorException($errstr);
        });

        try {
            $this->expectException(DirectoryNotWritable::class);

            $this->callMethod('isFileWritable', [$blockerFile . '/subdir/log.txt']);
        } finally {
            restore_error_handler();
            unlink($blockerFile);
        }
    }

    public function testIsFileWritableThrowsWhenDirectoryNotWritable(): void
    {
        $readOnlyDir = WORKINGDIR . '/logreadonlydir';

        if (!is_dir($readOnlyDir)) {
            mkdir($readOnlyDir);
        }
        chmod($readOnlyDir, 0555);

        try {
            $this->expectException(DirectoryNotWritable::class);

            $this->callMethod('isFileWritable', [$readOnlyDir . '/log.txt']);
        } finally {
            chmod($readOnlyDir, 0755);
            rmdir($readOnlyDir);
        }
    }
}
