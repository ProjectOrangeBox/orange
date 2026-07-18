<?php

declare(strict_types=1);

use orange\framework\Application;
use orange\framework\interfaces\ContainerInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

#[RunTestsInSeparateProcesses]
final class ApplicationTest extends \UnitTestHelper
{
    public function testMakeReturnsSingletonInstance(): void
    {
        $app1 = Application::make();
        $app2 = Application::make();

        $this->assertSame($app1, $app2);
    }

    public function testLoadEnvironmentLoadsIniValues(): void
    {
        $envFile = WORKINGDIR . '/env/test_env.ini';
        file_put_contents($envFile, "FOO=bar\n");

        $app = Application::make([$envFile]);

        $this->assertEquals('bar', $app->env('FOO'));

        unlink($envFile);
    }

    public function testMakeSetsConfigDirectories(): void
    {
        $app = Application::make(null, [WORKINGDIR . '/config']);

        $dirs = $this->getPrivatePublic('configDirectories', $app);

        $this->assertContains(WORKINGDIR . '/config', $dirs);
    }

    public function testRunBootstrapsCliApplicationUsingFrameworkDefaults(): void
    {
        // no app-level config directory is registered, so setConfigDirectories()'s
        // defaults (__ROOT__/config, __ROOT__/config/{ENVIRONMENT}) don't exist and
        // only the framework's own bundled src/config is used - a full, self
        // contained bootstrap() run (constants, container, services) end to end
        $app = Application::make();

        $container = $app->run([]);

        // bootstrap() -> preContainer() installs global error/exception handlers as
        // a side effect; pop them back off (not set_*_handler(null), which would drop
        // PHPUnit's own handler too and get this test flagged as risky) so they can't
        // interfere with the rest of this process
        restore_error_handler();
        restore_exception_handler();

        $this->assertInstanceOf(ContainerInterface::class, $container);
        $this->assertEquals('UTF-8', $container->get('$application')['encoding']);
    }
}
