<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use orange\framework\helpers\Ary;

class AryTest extends TestCase
{
    public function testRemapKey(): void
    {
        $input = ['a' => 1, 'b' => 2, 'c' => 3];
        $map = ['a' => 'alpha', 'c' => 'charlie'];

        $result = Ary::remapKey($input, $map);

        $this->assertEquals(['alpha' => 1, 'b' => 2, 'charlie' => 3], $result);
    }

    public function testRemapValue(): void
    {
        $input = ['a' => 'x', 'b' => 'y', 'c' => 'z'];
        $map = ['x' => 'ex', 'z' => 'zee'];

        $result = Ary::remapValue($input, $map);

        $this->assertEquals(['a' => 'ex', 'b' => 'y', 'c' => 'zee'], $result);
    }

    public function testWrapArray(): void
    {
        $array = ['one', 'two', 'three'];

        $result = Ary::wrapArray($array, '[', ']', ', ', '<', '>');

        $this->assertEquals('<[one], [two], [three]>', $result);
    }

    public function testMakeAssociatedWithArrays(): void
    {
        $array = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = Ary::makeAssociated($array, 'id', 'name');

        $this->assertEquals([1 => 'Alice', 2 => 'Bob'], $result);
    }

    public function testMakeAssociatedWithObjects(): void
    {
        $obj1 = (object) ['id' => 1, 'name' => 'Alice'];
        $obj2 = (object) ['id' => 2, 'name' => 'Bob'];

        $array = [$obj1, $obj2];

        $result = Ary::makeAssociated($array, 'id', 'name');

        $this->assertEquals([1 => 'Alice', 2 => 'Bob'], $result);
    }

    public function testMakeAssociatedWithAsterisk(): void
    {
        $array = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = Ary::makeAssociated($array, 'id', '*');

        $this->assertEquals([1 => ['id' => 1, 'name' => 'Alice'], 2 => ['id' => 2, 'name' => 'Bob']], $result);
    }

    public function testMakeAssociatedWithSort(): void
    {
        $array = [
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 1, 'name' => 'Alice'],
        ];

        $result = Ary::makeAssociated($array, 'id', 'name', 'asc');

        $this->assertEquals([1 => 'Alice', 2 => 'Bob'], $result);
    }

    public function testMakeAssociatedWithSortDesc(): void
    {
        $array = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        $result = Ary::makeAssociated($array, 'id', 'name', 'desc');

        $this->assertEquals([2 => 'Bob', 1 => 'Alice'], $result);
    }

    public function testMakeAssociatedWithSortAndExplicitFlags(): void
    {
        // makeAssociated() used to call $sortFunction() a second time unconditionally
        // after the if/else already sorted once, passing the raw $flags (-1 when none
        // was given) as an invalid sort flag - remove the double call and this must
        // still sort correctly with an explicit flag
        $array = [
            ['id' => 10, 'name' => 'Bob'],
            ['id' => 2, 'name' => 'Alice'],
        ];

        $result = Ary::makeAssociated($array, 'id', 'name', 'asc', SORT_STRING);

        $this->assertEquals([10 => 'Bob', 2 => 'Alice'], $result);
    }

    public function testElement(): void
    {
        $array = ['a' => 'value', 'b' => 'other'];

        $this->assertEquals('value', Ary::element('a', $array));
        $this->assertEquals('default', Ary::element('missing', $array, 'default'));
    }

    public function testRandomElement(): void
    {
        $array = ['a', 'b', 'c'];

        $result = Ary::randomElement($array);

        $this->assertContains($result, $array);
    }

    public function testElements(): void
    {
        $array = ['a' => 1, 'b' => 2, 'c' => 3];

        $result = Ary::elements(['a', 'c'], $array);

        $this->assertEquals(['a' => 1, 'c' => 3], $result);

        $resultWithDefault = Ary::elements(['a', 'missing'], $array, 'not found');

        $this->assertEquals(['a' => 1, 'missing' => 'not found'], $resultWithDefault);
    }
}
