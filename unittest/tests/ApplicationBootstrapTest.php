<?php

declare(strict_types=1);

use orange\framework\Application;
use orange\framework\Container;
use orange\framework\interfaces\ContainerInterface;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\IncorrectInterface;
use orange\framework\exceptions\filesystem\FileNotFound;
use orange\framework\exceptions\config\ConfigFileDidNotReturnAnArray;

/**
 * Exercises the individual bootstrap helpers of Application without running the
 * full http()/run() lifecycle (which sends output and defines global state).
 *
 * @runTestsInSeparateProcesses
 */
final class ApplicationBootstrapTest extends \UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = Application::make();
    }

    /* get() / make() */

    public function testGetReturnsSameInstanceAsMake(): void
    {
        $this->assertSame(Application::make(), Application::get());
    }

    /* env() */

    public function testEnvReturnsDefaultForMissingKey(): void
    {
        $this->assertEquals('fallback', $this->instance->env('NO_SUCH_ENV_KEY', 'fallback'));
    }

    public function testEnvReturnsNullDefaultWhenUnset(): void
    {
        $this->assertNull($this->instance->env('NO_SUCH_ENV_KEY'));
    }

    public function testEnvReadsLoadedValues(): void
    {
        // loadEnvironment() copies $_ENV; seed a value then force a fresh load
        $this->setPrivatePublic('env', []);
        $_ENV['SEEDED'] = 'seeded-value';

        $this->instance->loadEnvironment();

        $this->assertEquals('seeded-value', $this->instance->env('SEEDED'));
    }

    /* loadEnvironmentFile() */

    public function testLoadEnvironmentFileMissingThrows(): void
    {
        $this->expectException(FileNotFound::class);

        $this->callMethod('loadEnvironmentFile', ['/does/not/exist.ini']);
    }

    public function testLoadEnvironmentFileMergesIniValues(): void
    {
        $ini = WORKINGDIR . '/env/app_boot_test.ini';
        file_put_contents($ini, "ALPHA=one\nBETA=two\n");

        try {
            $this->setPrivatePublic('env', []);
            $this->callMethod('loadEnvironmentFile', [$ini]);

            $this->assertEquals('one', $this->instance->env('ALPHA'));
            $this->assertEquals('two', $this->instance->env('BETA'));
        } finally {
            unlink($ini);
        }
    }

    /* setConfigDirectories() / loadConfigFile() */

    public function testSetConfigDirectoriesPrependsOrangeConfig(): void
    {
        $this->callMethod('setConfigDirectories', [WORKINGDIR . '/config']);

        $dirs = $this->getPrivatePublic('configDirectories');

        // the framework's own src/config is always searched first
        $this->assertStringEndsWith('src' . DIRECTORY_SEPARATOR . 'config', $dirs[0]);
        $this->assertContains(WORKINGDIR . '/config', $dirs);
    }

    public function testLoadConfigFileReturnsMergedArray(): void
    {
        $config = $this->callMethod('loadConfigFile', ['application']);

        // keys from the framework's default src/config/application.php
        $this->assertIsArray($config);
        $this->assertArrayHasKey('encoding', $config);
        $this->assertArrayHasKey('timezone', $config);
    }

    public function testLoadConfigFileUnknownReturnsEmptyArray(): void
    {
        $config = $this->callMethod('loadConfigFile', ['this_config_does_not_exist']);

        $this->assertEquals([], $config);
    }

    public function testLoadConfigFileNonArrayThrows(): void
    {
        // point the loader at a directory containing a config file that returns a string
        $badDir = WORKINGDIR . '/config/badreturn';
        @mkdir($badDir, 0777, true);
        file_put_contents($badDir . '/broken.php', "<?php\nreturn 'not an array';\n");

        try {
            $this->setPrivatePublic('configDirectories', [$badDir]);

            $this->expectException(ConfigFileDidNotReturnAnArray::class);
            $this->callMethod('loadConfigFile', ['broken']);
        } finally {
            unlink($badDir . '/broken.php');
            rmdir($badDir);
        }
    }

    /* bootstrapContainer() */

    public function testBootstrapContainerBuildsContainerFromClosure(): void
    {
        $services = [
            'container' => fn(array $services): ContainerInterface => Container::getInstance($services),
            'foo' => 'bar',
        ];

        $this->callMethod('bootstrapContainer', [$services]);

        $container = $this->getPrivatePublic('container');
        $this->assertInstanceOf(ContainerInterface::class, $container);
        $this->assertEquals('bar', $container->get('foo'));
    }

    public function testBootstrapContainerMissingServiceThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Container Service not found.');

        $this->callMethod('bootstrapContainer', [[]]);
    }

    public function testBootstrapContainerNonClosureThrows(): void
    {
        $this->expectException(IncorrectInterface::class);
        $this->expectExceptionMessage('not a closure');

        $this->callMethod('bootstrapContainer', [['container' => 'i am not a closure']]);
    }

    public function testBootstrapContainerClosureReturningNonContainerThrows(): void
    {
        // NOTE: the typed property assignment ($this->container = ...) rejects a
        // non-container with a TypeError before the explicit instanceof check on
        // Application.php:368 is reached, so that guard is currently dead code.
        $this->expectException(\TypeError::class);

        $this->callMethod('bootstrapContainer', [['container' => fn($s) => new stdClass()]]);
    }

    /* postContainer() */

    public function testPostContainerExposesApplicationConfig(): void
    {
        $container = Container::getInstance([]);
        $this->setPrivatePublic('container', $container);
        $this->setPrivatePublic('config', ['name' => 'orange', 'debug' => true]);

        $this->callMethod('postContainer');

        $this->assertEquals(['name' => 'orange', 'debug' => true], $container->get('$application'));
    }
}
