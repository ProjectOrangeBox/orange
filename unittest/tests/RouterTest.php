<?php

declare(strict_types=1);

use orange\framework\Router;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\MissingRequired;
use orange\framework\exceptions\router\RouteNotFound;
use orange\framework\exceptions\router\RouterNameNotFound;
use orange\framework\Input;

final class RouterTest extends UnitTestHelper
{
    protected $instance;
    protected $callback = ['\app\controllers\controller', 'index'];

    protected function setUp(): void
    {
        $config = [
            'site url' => 'www.example.com',
            'routes' => [
                ['method' => '*', 'name' => 'product', 'url' => '/product/([a-z]+)/(\d+)'],

                ['method' => 'get', 'name' => 'productg', 'url' => '/getter/([a-z]+)/(\d+)', 'callback' => $this->callback],
                ['method' => 'post', 'name' => 'productp', 'url' => '/poster', 'callback' => $this->callback],
                ['method' => 'put', 'url' => '/putter/([a-z]+)/(\d+)', 'callback' => $this->callback],
                ['method' => 'delete', 'name' => 'productd', 'url' => '/remove/([a-z]+)', 'callback' => $this->callback],
                ['method' => 'head', 'name' => 'producth', 'url' => '/view/([a-z]+)', 'callback' => $this->callback],
            ],
            // remove the defaults!!
            '404' => [],
            'home' => [],
        ];

        // don't force a https switch in the input service
        $this->instance = Router::getInstance($config, Input::getInstance([
            'force https' => false,
        ]));
    }

    // Tests
    public function testMatch1(): void
    {
        $this->instance->match('/product/abc/123', 'get');

        $this->assertEquals('GET', $this->instance->getMatched('request Method'));
        $this->assertEquals('/product/abc/123', $this->instance->getMatched('request URI'));
        $this->assertEquals('/product/([a-z]+)/(\d+)', $this->instance->getMatched('matched URI'));
        $this->assertEquals('product', $this->instance->getMatched('name'));
        $this->assertEquals(null, $this->instance->getMatched('callback'));
    }

    public function testMatch2(): void
    {
        $this->instance->match('/getter/abc/123', 'get');

        $this->assertEquals('GET', $this->instance->getMatched('request Method'));
        $this->assertEquals('/getter/abc/123', $this->instance->getMatched('request URI'));
        $this->assertEquals('/getter/([a-z]+)/(\d+)', $this->instance->getMatched('matched URI'));
        $this->assertEquals('productg', $this->instance->getMatched('name'));
        $this->assertEquals($this->callback, $this->instance->getMatched('callback'));
    }

    public function testMatch3(): void
    {
        $this->instance->match('/poster', 'POST');

        $this->assertEquals('POST', $this->instance->getMatched('request Method'));
        $this->assertEquals('/poster', $this->instance->getMatched('request URI'));
        $this->assertEquals('/poster', $this->instance->getMatched('matched URI'));
        $this->assertEquals('productp', $this->instance->getMatched('name'));
        $this->assertEquals($this->callback, $this->instance->getMatched('callback'));
    }

    public function testMatch4(): void
    {
        $this->instance->match('/putter/abc/123', 'pUt');

        $this->assertEquals('PUT', $this->instance->getMatched('request Method'));
        $this->assertEquals('/putter/abc/123', $this->instance->getMatched('request URI'));
        $this->assertEquals('/putter/([a-z]+)/(\d+)', $this->instance->getMatched('matched URI'));
        $this->assertEquals(null, $this->instance->getMatched('name'));
        $this->assertEquals($this->callback, $this->instance->getMatched('callback'));
    }

    public function testMatch5(): void
    {
        $this->instance->match('/remove/rty', 'delete');

        $this->assertEquals('DELETE', $this->instance->getMatched('request Method'));
        $this->assertEquals('/remove/rty', $this->instance->getMatched('request URI'));
        $this->assertEquals('/remove/([a-z]+)', $this->instance->getMatched('matched URI'));
        $this->assertEquals('productd', $this->instance->getMatched('name'));
        $this->assertEquals($this->callback, $this->instance->getMatched('callback'));
    }

    public function testGetUrl(): void
    {
        $this->assertEquals('/product/xyz/890', $this->instance->getUrl('product', ['xyz', 890], false));
        $this->assertEquals('/poster', $this->instance->getUrl('productp', [], false));
        $this->assertEquals('/view/xyz', $this->instance->getUrl('producth', ['xyz'], false));
        $this->assertEquals('http://www.example.com/view/xyz', $this->instance->siteUrl() . $this->instance->getUrl('producth', ['xyz'], true));
    }

    public function testSiteUrl(): void
    {
        $this->assertEquals('http://www.example.com', $this->instance->siteUrl(true));
        $this->assertEquals('www.example.com', $this->instance->siteUrl(false));
        $this->assertEquals('ftps://www.example.com', $this->instance->siteUrl('ftps://'));
    }

    public function testGetMatchedInvalidValueException(): void
    {
        $this->instance->match('/product/abc/123', 'get');

        $this->expectException(InvalidValue::class);

        $this->instance->getMatched('foobar');
    }

    public function testGetUrlInvalidValueException(): void
    {
        $this->expectException(InvalidValue::class);

        $this->instance->getUrl('product', [890], false);
    }

    public function testGetUrlParameterInvalidValueException(): void
    {
        $this->expectException(InvalidValue::class);

        $this->instance->getUrl('product', ['abc', 'xyz'], false);
    }

    public function testMatchAfterSuccessfulMatchStillThrowsWhenNoRouteFound(): void
    {
        // regression guard: match() must reset any previous match's data before
        // trying again. Without that, a failed match here would inherit the prior
        // successful match's (truthy) url, silently skip RouteNotFound, and leave
        // the OLD, unrelated match active - relevant to any long-running process
        // that reuses the same Router instance across more than one request.
        $this->instance->match('/product/abc/123', 'get');
        $this->assertEquals('product', $this->instance->getMatched('name'));

        $this->expectException(RouteNotFound::class);

        $this->instance->match('/this/matches/nothing', 'get');
    }

    public function testMatchRouteNotFoundException1(): void
    {
        $this->expectException(RouteNotFound::class);

        $this->instance->match('/bla/bla/bla', 'GET');
    }

    public function testMatchRouteNotFoundException2(): void
    {
        $this->expectException(RouteNotFound::class);

        $this->instance->match('/poster', 'GET');
    }

    public function testGetUrlRouterNameNotFound(): void
    {
        $this->expectException(RouterNameNotFound::class);

        $this->instance->getUrl('notreal');
    }

    public function testConfigNotFoundException(): void
    {
        $this->expectException(MissingRequired::class);

        Router::newInstance(['site url' => ''], Input::newInstance([]));
    }

    public function testAddRouteThenMatch(): void
    {
        $this->instance->addRoute(['method' => 'get', 'name' => 'extra', 'url' => '/extra/([a-z]+)', 'callback' => $this->callback]);

        $this->instance->match('/extra/hello', 'get');

        $this->assertEquals('extra', $this->instance->getMatched('name'));
    }

    public function testAddRoutes(): void
    {
        $this->instance->addRoutes([
            ['method' => 'get', 'name' => 'r1', 'url' => '/r1', 'callback' => $this->callback],
            ['method' => 'get', 'name' => 'r2', 'url' => '/r2', 'callback' => $this->callback],
        ]);

        $this->instance->match('/r2', 'get');

        $this->assertEquals('r2', $this->instance->getMatched('name'));
    }

    public function testGetMatchedReturnsWholeArray(): void
    {
        $this->instance->match('/getter/abc/123', 'get');

        $matched = $this->instance->getMatched();

        $this->assertIsArray($matched);
        $this->assertEquals('productg', $matched['name']);
        $this->assertEquals(['abc', '123'], $matched['argv']);
    }

    public function testGetRouterCallback(): void
    {
        $this->instance->match('/getter/abc/123', 'get');

        $callback = $this->instance->getRouterCallback();

        $this->assertInstanceOf(\orange\framework\property\RouterCallback::class, $callback);
        $this->assertEquals($this->callback[0], $callback->controller);
        $this->assertEquals('index', $callback->method);
        $this->assertEquals(['abc', '123'], $callback->arguments);
    }

    public function testGetRouterCallbackWithoutCallbackThrows(): void
    {
        // the 'product' route has no callback defined
        $this->instance->match('/product/abc/123', 'get');

        $this->expectException(InvalidValue::class);
        $this->instance->getRouterCallback();
    }

    public function testSiteUrlWithoutPrefixReturnsBareUrl(): void
    {
        $this->assertEquals('www.example.com', $this->instance->siteUrl(false));
    }
}
