<?php

declare(strict_types=1);

use orange\framework\Data;

final class DataTest extends unitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = Data::getInstance();
    }

    public function testData(): void
    {
        $this->instance['name'] = 'Johnny Appleseed';

        $this->assertEquals('Johnny Appleseed', $this->instance['name']);
        $this->assertEquals(1, count($this->instance));
    }

    // Tests
    public function testMerge(): void
    {
        $this->instance->merge(['name' => 'Johnny Appleseed', 'age' => 21, 'food' => 'pizza']);

        $this->assertEquals(['name' => 'Johnny Appleseed', 'age' => 21, 'food' => 'pizza'], (array)$this->instance);
        $this->assertEquals(3, count($this->instance));

        $this->instance->merge(['name' => 'Jenny Appleseed', 'age' => 25]);

        $this->assertEquals(['name' => 'Jenny Appleseed', 'age' => 25, 'food' => 'pizza'], (array)$this->instance);
        $this->assertEquals(3, count($this->instance));
    }
}
