<?php

declare(strict_types=1);

use orange\framework\base\ArrayObject;
use orange\framework\exceptions\MagicMethodNotFound;

/**
 * Covers the ArrayObject helper methods (has/get/__get/__call/merge) beyond the
 * basic offset access exercised in BaseTest.
 */
final class ArrayObjectTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = ArrayObject::getInstance(['color' => 'blue', 'age' => 21]);
    }

    public function testHas(): void
    {
        $this->assertTrue($this->instance->has('color'));
        $this->assertFalse($this->instance->has('missing'));
    }

    public function testGetReturnsValue(): void
    {
        $this->assertEquals('blue', $this->instance->get('color', 'default'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->assertEquals('default', $this->instance->get('missing', 'default'));
    }

    public function testMagicGetReturnsValue(): void
    {
        $this->assertEquals('blue', $this->instance->color);
    }

    public function testMagicSet(): void
    {
        $this->instance->food = 'pizza';

        $this->assertEquals('pizza', $this->instance['food']);
    }

    public function testCallForwardsToArrayFunction(): void
    {
        // __call proxies array_* functions against the internal storage
        $keys = $this->instance->array_keys();

        $this->assertEqualsCanonicalizing(['color', 'age'], $keys);
    }

    public function testCallUnknownMethodThrows(): void
    {
        $this->expectException(MagicMethodNotFound::class);

        $this->instance->notAnArrayFunction();
    }

    public function testMergeRecursiveReplaces(): void
    {
        $this->instance->merge(['age' => 99, 'city' => 'here']);

        $this->assertEquals(99, $this->instance['age']);
        $this->assertEquals('here', $this->instance['city']);
        $this->assertEquals('blue', $this->instance['color']);
    }

    public function testMergeNonReplaceKeepsBothWithArrayMerge(): void
    {
        // replace = false uses array_merge_recursive, collapsing duplicate keys into arrays
        $this->instance->merge(['color' => 'red'], true, false);

        $this->assertEquals(['blue', 'red'], $this->instance['color']);
    }
}
