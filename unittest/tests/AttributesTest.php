<?php

declare(strict_types=1);

use orange\framework\attributes\Route;
use orange\framework\attributes\AutoWire;
use orange\framework\attributes\AttachService;
use orange\framework\property\RouterCallback;

/**
 * The attribute classes and RouterCallback are simple value objects, but they
 * form part of the public routing/container contract. These tests pin their
 * defaults, their promoted-property values and — for the attributes — that they
 * are actually readable back off a reflected target via getAttributes().
 */
final class AttributesTest extends UnitTestHelper
{
    /* Route */

    public function testRouteStoresConstructorValues(): void
    {
        $route = new Route(['GET', 'POST'], '/users', 'users.index');

        $this->assertEquals(['GET', 'POST'], $route->method);
        $this->assertEquals('/users', $route->url);
        $this->assertEquals('users.index', $route->name);
    }

    public function testRouteAcceptsStringMethod(): void
    {
        $route = new Route('GET', '/user/(?<id>.*)', 'users.show');

        $this->assertEquals('GET', $route->method);
    }

    public function testRouteDefaults(): void
    {
        $route = new Route();

        $this->assertEquals([], $route->method);
        $this->assertEquals('', $route->url);
        $this->assertEquals('', $route->name);
    }

    public function testRouteIsReadableViaReflection(): void
    {
        $target = new class {
            #[Route('GET', '/ping', 'ping')]
            public function ping(): void {}
        };

        $method = new ReflectionMethod($target, 'ping');
        $attributes = $method->getAttributes(Route::class);

        $this->assertCount(1, $attributes);

        $route = $attributes[0]->newInstance();

        $this->assertEquals('GET', $route->method);
        $this->assertEquals('/ping', $route->url);
        $this->assertEquals('ping', $route->name);
    }

    /* AutoWire */

    public function testAutoWireStoresService(): void
    {
        $autoWire = new AutoWire('database');

        $this->assertEquals('database', $autoWire->service);
    }

    public function testAutoWireIsReadableViaReflection(): void
    {
        $target = new class {
            #[AutoWire('logger')]
            public function boot(): void {}
        };

        $attributes = (new ReflectionMethod($target, 'boot'))->getAttributes(AutoWire::class);

        $this->assertEquals('logger', $attributes[0]->newInstance()->service);
    }

    /* AttachService */

    public function testAttachServiceStoresService(): void
    {
        $attach = new AttachService('input');

        $this->assertEquals('input', $attach->attachService);
    }

    public function testAttachServiceIsReadableViaReflection(): void
    {
        $target = new class {
            #[AttachService('output')]
            public $out;
        };

        $attributes = (new ReflectionProperty($target, 'out'))->getAttributes(AttachService::class);

        $this->assertEquals('output', $attributes[0]->newInstance()->attachService);
    }

    /* RouterCallback */

    public function testRouterCallbackStoresConstructorValues(): void
    {
        $callback = new RouterCallback('HomeController', 'index', ['a', 'b']);

        $this->assertEquals('HomeController', $callback->controller);
        $this->assertEquals('index', $callback->method);
        $this->assertEquals(['a', 'b'], $callback->arguments);
    }

    public function testRouterCallbackAcceptsEmptyArguments(): void
    {
        $callback = new RouterCallback('FooController', 'bar', []);

        $this->assertEquals([], $callback->arguments);
    }
}
