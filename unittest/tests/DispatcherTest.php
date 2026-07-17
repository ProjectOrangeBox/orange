<?php

declare(strict_types=1);

use orange\framework\Dispatcher;
use orange\framework\exceptions\dispatcher\ArgumentMissMatch;
use orange\framework\exceptions\dispatcher\ControllerClassNotFound;
use orange\framework\exceptions\dispatcher\MethodNotFound;
use orange\framework\property\RouterCallback;

final class DispatcherTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = Dispatcher::getInstance();

        include_once MOCKDIR . '/mockController.php';
    }

    // Tests
    public function testCall(): void
    {
        $this->assertEquals('<h1>foobar</h1>', $this->instance->call(new RouterCallback('mockController','index',[])));
    }

    public function testControllerClassNotFoundException(): void
    {
        $this->expectException(ControllerClassNotFound::class);

        $this->assertNull($this->instance->call(new RouterCallback('foobar','index',[])));
    }

    public function testMethodNotFoundException(): void
    {
        $this->expectException(MethodNotFound::class);

        $this->assertNull($this->instance->call(new RouterCallback('mockController','foobar',[])));
    }

    public function testProtectedMethodThrowsMethodNotFound(): void
    {
        // method_exists() doesn't check visibility - a route pointing at a
        // protected/private method must be treated as not found, not left to
        // fatal with an uncaught Error when actually invoked
        $this->expectException(MethodNotFound::class);

        $this->instance->call(new RouterCallback('mockController', 'secret', []));
    }

    public function testMethodPassOne(): void
    {
        $this->assertEquals('one', $this->instance->call(new RouterCallback('mockController','passone',['one'])));
    }

    public function testMethodPassTwo(): void
    {
        $this->assertEquals('one+two', $this->instance->call(new RouterCallback('mockController','passtwo',['one','two'])));
    }

    public function testMethodPassOneNull(): void
    {
        $this->assertEquals('one+', $this->instance->call(new RouterCallback('mockController','passonenull',['one'])));
    }

    public function testMethodPassTwoEmpty(): void
    {
        $this->expectException(ArgumentMissMatch::class);

        $this->assertEquals('+', $this->instance->call(new RouterCallback('mockController','passtwo',[])));
    }
}
