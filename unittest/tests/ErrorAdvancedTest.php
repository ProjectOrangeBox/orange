<?php

declare(strict_types=1);

use orange\framework\Data;
use orange\framework\View;
use orange\framework\Input;
use orange\framework\Error;
use orange\framework\ViewFinder;
use orange\framework\Router;
use orange\framework\Container;
use orange\framework\interfaces\DataInterface;
use orange\framework\interfaces\InputInterface;

/**
 * Covers Error's view-resolution and service-resolution helpers (findView,
 * renderViewBasedOnCode, getService) which need a real View/Container wired up.
 * The constructor itself calls exit(), so the instance is built without it.
 */
final class ErrorAdvancedTest extends unitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = (new ReflectionClass(Error::class))->newInstanceWithoutConstructor();

        $view = View::getInstance(
            [
                'temp directory' => sys_get_temp_dir(),
                'debug' => false,
            ],
            Data::getInstance([]),
            Router::getInstance(['site url' => 'www.example.com'], Input::getInstance(['force https' => false])),
        );

        $this->setPrivatePublic('view', $view);
        // empty on purpose: an application that has not generated a view map has
        // nothing here, and the error views that ship beside Error.php still have
        // to resolve - see findView()'s bundled fallback
        $this->setPrivatePublic('viewFinder', ViewFinder::newInstance([]));
        $this->setPrivatePublic('data', Data::getInstance([]));
        $this->setPrivatePublic('errorViewDirectory', 'errors');
        $this->setPrivatePublic('envDirectory', 'production');
        $this->setPrivatePublic('requestType', 'html');
        $this->setPrivatePublic('requestTypeDirectory', 'html');
    }

    /* findView() */

    /**
     * findView() returns a path now, not a name - the view engine does not
     * search any more, so nothing downstream could resolve a name.
     */
    public function testFindViewLocatesRequestTypeView(): void
    {
        $found = $this->callMethod('findView', ['404']);

        $this->assertEquals(ORANGEDIR . '/views/errors/html/404.php', $found);
        $this->assertFileExists($found);
    }

    /**
     * With a view map that does hold the name, the application's copy wins -
     * error pages are overridable exactly like any other view.
     */
    public function testFindViewPrefersTheViewFinderOverTheBundledCopy(): void
    {
        $this->setPrivatePublic('viewFinder', ViewFinder::newInstance([
            'view fallbacks' => ['errors/html/404' => WORKINGDIR . '/views/errors/html/404.php'],
        ]));

        $found = $this->callMethod('findView', ['404']);

        $this->assertEquals(WORKINGDIR . '/views/errors/html/404.php', $found);
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
