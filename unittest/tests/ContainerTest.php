<?php

declare(strict_types=1);

use orange\framework\Container;
use orange\framework\interfaces\ContainerInterface;
use orange\framework\exceptions\container\ServiceNotFound;

final class ContainerTest extends unitTestHelper
{
    protected $instance;
    protected $services;

    protected function setUp(): void
    {
        $this->services = [
            'foo' => 'bar',
            'cookie' => function (ContainerInterface $container) {
                return new stdClass();
            },
             // alias of foo
             '@cat' => 'foo',
        ];

        $this->instance = Container::getInstance($this->services);
    }

    public function test__get(): void
    {
        $this->assertEquals('bar', $this->instance->foo);
        $this->assertEquals('bar', $this->instance->cat);
        $this->assertInstanceOf(stdClass::class, $this->instance->cookie);
    }

    public function testGet(): void
    {
        $this->assertEquals('bar', $this->instance->get('foo'));
        $this->assertEquals('bar', $this->instance->get('cat'));
        $this->assertInstanceOf(stdClass::class, $this->instance->get('cookie'));
    }

    public function test__set(): void
    {
        $this->instance->food = 'pizza';

        $this->assertEquals('pizza', $this->instance->get('food'));
    }

    public function testSet(): void
    {
        $this->instance->water = 'blue';

        $this->assertEquals('blue', $this->instance->water);
    }

    public function test__isset(): void
    {
        $this->assertTrue(isset($this->instance->foo));
        $this->assertFalse(isset($this->instance->nope));
    }

    public function testIsset(): void
    {
        $this->assertTrue($this->instance->isset('foo'));
        $this->assertFalse($this->instance->isset('nope'));
    }

    public function testHas(): void
    {
        $this->assertTrue($this->instance->has('foo'));
        $this->assertTrue($this->instance->has('cookie'));
        $this->assertFalse($this->instance->has('invalid'));
    }

    public function test__unset(): void
    {
        unset($this->instance->foo);

        $this->assertFalse($this->instance->has('foo'));
    }

    public function testUnset(): void
    {
        $this->instance->unset('foo');

        $this->assertFalse($this->instance->has('foo'));
    }

    public function testRemove(): void
    {
        $this->instance->remove('foo');

        $this->assertFalse($this->instance->has('foo'));
    }

    public function testServiceNotFoundException(): void
    {
        $this->expectException(ServiceNotFound::class);

        $this->instance->get('bogus service');
    }
}
