<?php

declare(strict_types=1);

use orange\framework\Data;
use orange\framework\View;
use orange\framework\Input;
use orange\framework\Error;
use orange\framework\Router;
use orange\framework\Container;
use orange\framework\interfaces\DataInterface;
use orange\framework\interfaces\InputInterface;

/**
 * Covers Error's view-resolution and service-resolution helpers (findView,
 * renderViewBasedOnCode, getService) which need a real View/Container wired up.
 * The constructor itself calls exit(), so the instance is built without it.
 */
final class ErrorAdvancedTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = (new ReflectionClass(Error::class))->newInstanceWithoutConstructor();

        $view = View::getInstance(
            [
                'view paths' => [WORKINGDIR . '/views'],
                'view aliases' => [],
                'temp directory' => sys_get_temp_dir(),
                'debug' => false,
            ],
            Data::getInstance([]),
            Router::getInstance(['site url' => 'www.example.com'], Input::getInstance(['force https' => false])),
        );

        $this->setPrivatePublic('view', $view);
        $this->setPrivatePublic('data', Data::getInstance([]));
        $this->setPrivatePublic('errorViewDirectory', 'errors');
        $this->setPrivatePublic('envDirectory', 'production');
        $this->setPrivatePublic('requestType', 'html');
        $this->setPrivatePublic('requestTypeDirectory', 'html');
    }

    /* findView() */

    public function testFindViewLocatesRequestTypeView(): void
    {
        // the framework ships src/views/errors/html/404.php
        $found = $this->callMethod('findView', ['404']);

        $this->assertEquals('errors/html/404', $found);
    }

    public function testFindViewReturnsEmptyWhenNotFound(): void
    {
        $found = $this->callMethod('findView', ['no-such-error-code']);

        $this->assertEquals('', $found);
    }

    /* renderViewBasedOnCode() */

    public function testRenderViewBasedOnCodeRendersFoundTemplate(): void
    {
        $rendered = $this->callMethod('renderViewBasedOnCode', [500, 0]);

        // rendered output of src/views/errors/html/500.php (non-empty string)
        $this->assertIsString($rendered);
        $this->assertNotEmpty($rendered);
    }

    public function testRenderViewBasedOnCodeUsesHttpCodeWhenProvided(): void
    {
        $rendered = $this->callMethod('renderViewBasedOnCode', [999, 404]);

        // httpCode 404 wins over code 999 and resolves the 404 template
        $this->assertIsString($rendered);
        $this->assertNotEmpty($rendered);
    }

    public function testRenderViewBasedOnCodeFallsBackToRawWhenNoTemplate(): void
    {
        $this->setPrivatePublic('data', Data::getInstance([])->merge(['code' => 12345, 'message' => 'raw fallback']));

        $rendered = $this->callMethod('renderViewBasedOnCode', [12345, 0]);

        // no template for 12345 -> viewRaw() html output
        $this->assertStringContainsString('raw fallback', $rendered);
    }

    /* getService() */

    public function testGetServiceReturnsFromContainerWhenRegistered(): void
    {
        $container = Container::getInstance(['data' => Data::getInstance([])]);
        $this->setPrivatePublic('container', $container);

        $service = $this->callMethod('getService', ['data', []]);

        $this->assertInstanceOf(DataInterface::class, $service);
    }

    public function testGetServiceFallsBackToOrangeClass(): void
    {
        // container without 'input' -> getService loads the orange Input class
        $container = Container::getInstance([]);
        $this->setPrivatePublic('container', $container);

        $service = $this->callMethod('getService', ['input', [[]]]);

        $this->assertInstanceOf(InputInterface::class, $service);
    }
}
