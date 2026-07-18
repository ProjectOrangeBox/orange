<?php

declare(strict_types=1);

use orange\framework\Application;
use orange\framework\interfaces\ContainerInterface;

/**
 * Exercises Application::http() end to end. It replaces the default
 * 'container' service (see working/config/apphttptest/services.php) with a
 * minimal one wired to a single matching route so routing, dispatching and
 * output all run for real without needing an actual web server request.
 *
 * @runTestsInSeparateProcesses
 */
final class ApplicationHttpTest extends \UnitTestHelper
{
    public function testHttpRunsFullRequestLifecycle(): void
    {
        $app = Application::make(null, [WORKINGDIR . '/config/apphttptest']);

        ob_start();
        $container = $app->http([]);
        $output = ob_get_clean();

        // bootstrap() -> preContainer() installs global error/exception handlers as
        // a side effect; restore the previous ones immediately so they can't
        // interfere with the rest of this process
        set_error_handler(null);
        set_exception_handler(null);

        $this->assertInstanceOf(ContainerInterface::class, $container);
        // mockController::index() (unittest/mocks/mockController.php)
        $this->assertEquals('<h1>foobar</h1>', $output);
    }
}
