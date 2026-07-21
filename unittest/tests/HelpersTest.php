<?php

declare(strict_types=1);

use orange\framework\Log;
use orange\framework\Config;
use orange\framework\Container;

final class HelpersTest extends UnitTestHelper
{
    private $testFile = '';

    protected function setUp(): void
    {
        require_once ORANGEDIR . '/helpers/Ary.php';
        require_once ORANGEDIR . '/helpers/Dot.php';
        require_once ORANGEDIR . '/helpers/errors.php';
        require_once ORANGEDIR . '/helpers/helpers.php';
        require_once ORANGEDIR . '/helpers/wrappers.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    // Tests
    public function testContainer(): void
    {
        $this->assertInstanceOf(Container::class, container());
    }

    public function testLogMsg(): void
    {
        $this->testFile = WORKINGDIR . '/writeable/test-log.txt';

        // setup config
        $log = Log::getInstance([
            'filepath' => $this->testFile,
            'threshold' => 255,
            'line format' => '12:00am %level %message',
            'timestamp format' => 'Y-m-d H:i:s',
        ]);

        // get an instance of container
        $container = Container::getInstance();

        // add config to container as config service
        $container->set('log', $log);

        // test away!
        logMsg(LOG::ALERT, 'This is an Alert!');

        $this->assertEquals('12:00am ALERT This is an Alert!', file_get_contents($this->testFile));
    }

    public function testIsLogEnabledMatchesThreshold(): void
    {
        $this->testFile = WORKINGDIR . '/writeable/test-log.txt';

        $log = Log::getInstance([
            'filepath' => $this->testFile,
            'threshold' => LOG::ALERT, // DEBUG is not in the threshold
            'line format' => '%level %message',
        ]);

        $container = Container::getInstance();
        $container->set('log', $log);

        $this->assertTrue(isLogEnabled(LOG::ALERT));
        $this->assertFalse(isLogEnabled(LOG::DEBUG));
    }

    public function testIsLogEnabledGuardSkipsBuildingMessageWhenDisabled(): void
    {
        $this->testFile = WORKINGDIR . '/writeable/test-log.txt';

        $log = Log::getInstance([
            'filepath' => $this->testFile,
            'threshold' => LOG::ALERT, // DEBUG is not in the threshold
            'line format' => '%level %message',
        ]);

        $container = Container::getInstance();
        $container->set('log', $log);

        $built = false;

        // this is the if (isLogEnabled(...)) { logMsg(...) } pattern call sites use
        if (isLogEnabled(LOG::DEBUG)) {
            $built = true;
            logMsg(LOG::DEBUG, 'should never be built');
        }

        $this->assertFalse($built);
        $this->assertFileDoesNotExist($this->testFile);
    }

    public function testIsLogEnabledCacheDoesNotGoStaleAfterChangeThreshold(): void
    {
        $this->testFile = WORKINGDIR . '/writeable/test-log.txt';

        // start disabled, so isLevelEnabled('DEBUG') memoizes a `false` answer
        $log = Log::getInstance([
            'filepath' => $this->testFile,
            'threshold' => 0,
            'line format' => '%level %message',
        ]);

        $container = Container::getInstance();
        $container->set('log', $log);

        $this->assertFalse(isLogEnabled(LOG::DEBUG));

        // enabling DEBUG must invalidate the memoized answer, not keep returning
        // the stale `false` cached above
        $log->changeThreshold(LOG::DEBUG);

        $this->assertTrue(isLogEnabled(LOG::DEBUG));

        if (isLogEnabled(LOG::DEBUG)) {
            logMsg(LOG::DEBUG, 'now enabled');
        }

        $this->assertStringContainsString('now enabled', file_get_contents($this->testFile));
    }

    public function testFile_put_contents_atomic(): void
    {
        $testFile = WORKINGDIR . '/writeable/test.txt';

        $this->assertEquals(6, file_put_contents_atomic($testFile, 'foobar'));
    }

    public function testConcat(): void
    {
        $this->assertEquals('Johnny.Appleseed', concat('Johnny', '.', 'Appleseed'));
    }

    public function testConfig(): void
    {
        // setup config
        // NOTE: this was previously passed as a plain list ([WORKINGDIR . '/env']) instead
        // of ['config directories' => [...]], so Config had zero search directories and
        // could never find configExample2.php - masked by the config() bug below, which
        // always threw and returned $default regardless.
        $config = Config::getInstance(['config directories' => [WORKINGDIR . '/env']]);

        // get an instance of container
        $container = Container::getInstance();

        // add config to container as config service
        $container->set('config', $config);

        // test away!
        // NOTE: previously commented out - config() called $configInstance->config
        // (Config::__get('config'), a nonexistent config file returning []) instead of
        // $configInstance itself, so ->get() on that empty array threw and every call
        // silently fell back to $default. Fixed - this now returns the real value.
        $this->assertEquals('Jenny Appleseed', config('configExample2', 'name'));
        $this->assertEquals('', config('configExample2', 'dummy'));
        $this->assertEquals('bar', config('configExample2', 'foo', 'bar'));
        $this->assertEquals(['name' => 'Jenny Appleseed'], config('configExample2'));
        $this->assertInstanceOf(Config::class, config());
    }
}
