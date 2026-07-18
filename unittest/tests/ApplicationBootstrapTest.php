<?php

declare(strict_types=1);

use orange\framework\Application;
use orange\framework\Container;
use orange\framework\interfaces\ContainerInterface;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\IncorrectInterface;
use orange\framework\exceptions\filesystem\FileNotFound;
use orange\framework\exceptions\config\ConfigFileDidNotReturnAnArray;
use orange\framework\exceptions\config\InvalidConfigurationValue;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises the individual bootstrap helpers of Application without running the
 * full http()/run() lifecycle (which sends output and defines global state).
 */
#[RunTestsInSeparateProcesses]
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

    /* preContainer() */

    public function testPreContainerRegistersErrorAndExceptionHandlers(): void
    {
        // errorHandler()/exceptionHandler() are already declared by unittest bootstrap.php
        $this->setPrivatePublic('config', ['helpers' => [], 'required helpers' => []]);

        $this->callMethod('preContainer');

        // preContainer() registers these via first-class callable syntax (errorHandler(...)),
        // which wraps them in a Closure. set_error_handler() always returns whatever was
        // active before the call while pushing a new one on PHP's handler stack, so pushing
        // a disposable no-op handler is how we peek at the current one; restore_error_handler()
        // then pops it back off. A second restore_*_handler() call pops preContainer()'s own
        // registration too, leaving PHPUnit's own handler in place exactly as it was pre-test -
        // PHPUnit 13 flags a test as risky if it leaves a foreign handler installed.
        $errorHandler = set_error_handler(fn () => null);
        restore_error_handler();
        restore_error_handler();

        $exceptionHandler = set_exception_handler(fn () => null);
        restore_exception_handler();
        restore_exception_handler();

        $this->assertEquals('errorHandler', (new ReflectionFunction($errorHandler))->getName());
        $this->assertEquals('exceptionHandler', (new ReflectionFunction($exceptionHandler))->getName());
    }

    public function testPreContainerThrowsFileNotFoundForMissingHelper(): void
    {
        $this->setPrivatePublic('config', ['helpers' => ['/does/not/exist/helper.php'], 'required helpers' => []]);

        $this->expectException(FileNotFound::class);

        $this->callMethod('preContainer');
    }

    public function testPreContainerIncludesConfiguredHelperFiles(): void
    {
        $helperFile = WORKINGDIR . '/env/preContainerHelper.php';
        file_put_contents($helperFile, "<?php\n// intentionally empty test helper\n");

        $this->setPrivatePublic('config', ['helpers' => [$helperFile], 'required helpers' => []]);

        try {
            $this->callMethod('preContainer');

            // reaching here without an exception means realpath()+include_once succeeded
            $this->addToAssertionCount(1);
        } finally {
            // preContainer() installs global error/exception handlers as a side effect;
            // pop them back off (not set_*_handler(null), which would drop PHPUnit's own
            // handler too and get this test flagged as risky) so they can't interfere
            // with the rest of this process
            restore_error_handler();
            restore_exception_handler();
            unlink($helperFile);
        }
    }

    public function testPreContainerLoadsConstantsFromConfig(): void
    {
        $configDir = WORKINGDIR . '/config/precontainerconstants';
        @mkdir($configDir, 0777, true);
        file_put_contents($configDir . '/constants.php', "<?php\nreturn ['APP_BOOTSTRAP_TEST_CONSTANT' => 'hello'];\n");

        $this->setPrivatePublic('config', ['helpers' => [], 'required helpers' => []]);
        $this->setPrivatePublic('configDirectories', [$configDir]);

        try {
            $this->callMethod('preContainer');

            restore_error_handler();
            restore_exception_handler();

            $this->assertTrue(defined('APP_BOOTSTRAP_TEST_CONSTANT'));
            $this->assertEquals('hello', APP_BOOTSTRAP_TEST_CONSTANT);
        } finally {
            unlink($configDir . '/constants.php');
            rmdir($configDir);
        }
    }

    /* loadEnvironmentFile() */

    public function testLoadEnvironmentFileInvalidFormatThrows(): void
    {
        $badIni = WORKINGDIR . '/env/malformed.ini';
        // deliberately invalid INI syntax (unterminated section header) so
        // parse_ini_file() returns false instead of an array
        file_put_contents($badIni, "[unterminated\nFOO=bar");

        try {
            $this->expectException(InvalidConfigurationValue::class);

            @$this->callMethod('loadEnvironmentFile', [$badIni]);
        } finally {
            unlink($badIni);
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
        $this->expectException(IncorrectInterface::class);
        $this->expectExceptionMessage('did not return an object using the container interface');

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
