<?php

declare(strict_types=1);

use orange\framework\Application;
use orange\framework\interfaces\ContainerInterface;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Exercises Application::http() end to end. It replaces the default
 * 'container' service (see working/config/apphttptest/services.php) with a
 * minimal one wired to a single matching route so routing, dispatching and
 * output all run for real without needing an actual web server request.
 */
#[RunTestsInSeparateProcesses]
final class ApplicationHttpTest extends \UnitTestHelper
{
    public function testHttpRunsFullRequestLifecycle(): void
    {
        $app = Application::make(null, [WORKINGDIR . '/config/apphttptest']);

        ob_start();
        $container = $app->http([]);
        $output = ob_get_clean();

        // bootstrap() -> preContainer() installs global error/exception handlers as
        // a side effect; pop them back off (not set_*_handler(null), which would drop
        // PHPUnit's own handler too and get this test flagged as risky) so they can't
        // interfere with the rest of this process
        restore_error_handler();
        restore_exception_handler();

        $this->assertInstanceOf(ContainerInterface::class, $container);
        // mockController::index() (unittest/mocks/mockController.php)
        $this->assertEquals('<h1>foobar</h1>', $output);
    }
}
