<?php

declare(strict_types=1);

use orange\framework\Data;
use orange\framework\View;
use orange\framework\Input;
use orange\framework\Output;
use orange\framework\Container;
use orange\framework\exceptions\InvalidValue;
use orange\framework\attributes\AttachService;
use orange\framework\interfaces\ConfigInterface;
use orange\framework\ViewFinder;
use orange\framework\interfaces\ViewInterface;
use orange\framework\interfaces\RouterInterface;
use orange\framework\controllers\BaseController;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A controller with a $view property, which is what renderView() needs.
 * BaseController's constructor attaches services from the container, so the
 * container has to be populated before one is built.
 */
class ExposedController extends BaseController
{
    #[AttachService('view')]
    protected ViewInterface $view;

    public function callRenderView(string $view = '', array $data = []): string
    {
        return $this->renderView($view, $data);
    }

    public function callResolve(string $view): string
    {
        return $this->resolveDynamicView($view);
    }

    public function callViewNamespace(): string
    {
        return $this->viewNamespace();
    }
}

/** The same, minus the $view property - renderView() has nothing to render with. */
class ViewlessController extends BaseController
{
    public function callRenderView(string $view = ''): string
    {
        return $this->renderView($view);
    }
}

/**
 * resolveDynamicView() used to live on ViewAbstract, which needed a router
 * injected to do it. It moved here so the view layer could drop that dependency;
 * these are the tests that moved with it (they were ViewerTest's).
 */
final class BaseControllerTest extends unitTestHelper
{
    protected function register(array $callback): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getMatched')->willReturn($callback);

        $container = Container::getInstance();
        $container->set('router', $router);
        $container->set('input', Input::newInstance([]));
        $container->set('output', Output::newInstance([], Input::newInstance([])));
        $container->set('config', $this->createStub(ConfigInterface::class));
        $container->set('view', View::newInstance([
            'temp directory' => sys_get_temp_dir(),
            'debug' => false,
        ], Data::newInstance()));
        // renderView() resolves a name to a path through this before the view
        // engine sees it. Keyed the way the view detector would key the fixture
        // tree: namespaced for a module's own view, un-namespaced as fallback
        $container->set('viewFinder', ViewFinder::newInstance([
            'views' => ['exposed/test' => WORKINGDIR . '/views/test.php'],
            'view fallbacks' => ['test' => WORKINGDIR . '/views/test.php'],
        ]));
    }

    protected function controller(array $callback): ExposedController
    {
        $this->register($callback);

        return new ExposedController();
    }

    /* resolveDynamicView() */

    public function testResolveDynamicViewEmptyUsesControllerAndMethod(): void
    {
        // '' -> '$c/$m' -> 'home/index' (controller suffix stripped, lowercased)
        $this->assertEquals('home/index', $this->controller(['HomeController', 'index'])->callResolve(''));
    }

    public function testResolveDynamicViewReplacesPlaceholders(): void
    {
        $this->assertEquals('home/index', $this->controller(['HomeController', 'index'])->callResolve('$c/$m'));
    }

    public function testResolveDynamicViewReplacesMethodOnly(): void
    {
        $this->assertEquals('custom/edit', $this->controller(['HomeController', 'edit'])->callResolve('custom/$m'));
    }

    public function testResolveDynamicViewEndsWithSlashStar(): void
    {
        // 'foo/*' -> 'foo/$m' -> 'foo/save'
        $this->assertEquals('foo/save', $this->controller(['HomeController', 'save'])->callResolve('foo/*'));
    }

    public function testResolveDynamicViewEndsWithStarStar(): void
    {
        // 'foo/*/*' -> 'foo/$c/$m' -> 'foo/home/index'
        $this->assertEquals('foo/home/index', $this->controller(['HomeController', 'index'])->callResolve('foo/*/*'));
    }

    public function testResolveDynamicViewNamespacedControllerSegments(): void
    {
        // $1=app, $2=admin, $m=edit
        $this->assertEquals('app/admin/edit', $this->controller(['App\\Admin\\UserController', 'edit'])->callResolve('$1/$2/$m'));
    }

    public function testResolveDynamicViewLeavesAPlainNameAlone(): void
    {
        $this->assertEquals('main/index', $this->controller(['HomeController', 'index'])->callResolve('main/index'));
    }

    public function testResolveDynamicViewMissingMethodThrows(): void
    {
        $controller = $this->controller(['HomeController', null]);

        $this->expectException(InvalidValue::class);

        $controller->callResolve('$m');
    }

    public function testResolveDynamicViewMissingControllerThrows(): void
    {
        $controller = $this->controller([null, 'index']);

        $this->expectException(InvalidValue::class);

        $controller->callResolve('$c');
    }

    /* renderView() */

    public function testRenderViewResolvesAndRenders(): void
    {
        // '$c' -> 'test', matching the working/views/test.php fixture
        $controller = $this->controller(['TestController', 'render']);

        $this->assertEquals('<h1>Hello World</h1>', $controller->callRenderView('$c', ['hello' => 'Hello World']));
    }

    public function testRenderViewPassesAPlainNameStraightThrough(): void
    {
        $controller = $this->controller(['TestController', 'render']);

        $this->assertEquals('<h1>Hello World</h1>', $controller->callRenderView('test', ['hello' => 'Hello World']));
    }

    /* viewNamespace() */

    /**
     * The namespace handed to ViewFinder is the controller's own, minus the
     * trailing \controllers segment - which is exactly the prefix the view
     * detector builds from that module's views/ directory.
     *
     * viewNamespace() reads $this->reflection, so swapping that is enough to
     * ask "what would a controller in this namespace resolve against?" without
     * a fixture class per namespace shape.
     */
    #[DataProvider('namespaceProvider')]
    public function testViewNamespaceStripsTheControllersSegment(string $class, string $expected): void
    {
        $controller = $this->controller(['TestController', 'render']);

        $this->setPrivatePublic('reflection', new ReflectionClass($class), $controller);

        $this->assertEquals($expected, $controller->callViewNamespace());
    }

    public static function namespaceProvider(): array
    {
        return [
            // the shape every module controller has
            'module controller' => [\orange\framework\controllers\BaseController::class, 'orange/framework'],
            // no \controllers\ segment - falls back to the whole namespace
            'no controllers segment' => [\orange\framework\ViewFinder::class, 'orange/framework'],
            // nested deeper than one module
            'nested module' => [\orange\framework\exceptions\view\ViewNotFound::class, 'orange/framework/exceptions/view'],
        ];
    }

    /**
     * A controller in the global namespace has no module of its own, so every
     * lookup goes straight to the shared fallbacks.
     */
    public function testViewNamespaceIsEmptyWithoutANamespace(): void
    {
        $controller = $this->controller(['TestController', 'render']);

        $this->assertEquals('', $controller->callViewNamespace());
    }

    public function testRenderViewThrowsWithoutAViewProperty(): void
    {
        $this->register(['HomeController', 'index']);

        $controller = new ViewlessController();

        $this->expectException(InvalidValue::class);

        $controller->callRenderView('test');
    }
}
