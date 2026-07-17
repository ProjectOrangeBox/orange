<?php

declare(strict_types=1);

use orange\framework\base\ArrayObject;
use orange\framework\base\SingletonArrayObject;
use orange\framework\exceptions\container\CannotCloneSingleton;
use orange\framework\exceptions\container\CannotUnserializeSingleton;

final class BaseTest extends UnitTestHelper
{
    public function testCloningIsForbidden(): void
    {
        $this->expectException(CannotCloneSingleton::class);

        $object = ArrayObject::getInstance([]);
        clone $object;
    }

    public function testWakeupIsForbidden(): void
    {
        $this->expectException(CannotUnserializeSingleton::class);

        ArrayObject::getInstance([])->__wakeup();
    }

    public function testArrayObject(): void
    {
        $arrayObject = ArrayObject::getInstance([]);

        $this->assertEquals(0, count($arrayObject));

        $arrayObject['name'] = 'Johnny Appleseed';

        $this->assertEquals(1, count($arrayObject));

        $this->assertEquals('Johnny Appleseed', $arrayObject['name']);
    }

    public function testArrayObjectDifferent(): void
    {
        $arrayObject1 = ArrayObject::getInstance([]);

        $this->assertEquals(0, count($arrayObject1));

        $arrayObject1['name'] = 'Johnny Appleseed';

        $this->assertEquals(1, count($arrayObject1));

        $this->assertEquals('Johnny Appleseed', $arrayObject1['name']);

        $arrayObject2 = ArrayObject::getInstance([]);

        $this->assertEquals(0, count($arrayObject2));

        $arrayObject2['name'] = 'Jenny Appleseed';

        $this->assertEquals(1, count($arrayObject2));

        $this->assertEquals('Jenny Appleseed', $arrayObject2['name']);

        $this->assertNotEquals($arrayObject1, $arrayObject2);
    }

    public function testFactory(): void
    {
        require __DIR__ . '/../mocks/automobile.php';

        $auto1 = automobile::getInstance();
        $auto1->vin = '123ABC';

        $auto2 = automobile::getInstance();
        $auto2->vin = '789XYZ';

        $this->assertEquals('123ABC', $auto1->vin);
        $this->assertEquals('789XYZ', $auto2->vin);

        $this->assertNotEquals($auto1, $auto2);
    }

    public function testSingleton(): void
    {
        require __DIR__ . '/../mocks/theSameAutomobile.php';

        $location1 = theSameAutomobile::getInstance();
        $location1->vin = '123789';

        $this->assertEquals('123789', $location1->vin);

        $location2 = theSameAutomobile::getInstance();
        $location2->vin = 'ABCXYZ';

        $this->assertEquals('ABCXYZ', $location2->vin);
        $this->assertEquals('ABCXYZ', $location1->vin);

        $this->assertEquals($location1, $location2);
    }

    public function testSingletonArrayObject(): void
    {
        $arrayObject1 = SingletonArrayObject::getInstance([]);

        $this->assertEquals(0, count($arrayObject1));

        $arrayObject1['name'] = 'Johnny Appleseed';

        $this->assertEquals(1, count($arrayObject1));

        $this->assertEquals('Johnny Appleseed', $arrayObject1['name']);

        $arrayObject2 = SingletonArrayObject::getInstance([]);

        // because it is the same it should already have Johnny Appleseed
        $this->assertEquals(1, count($arrayObject2));

        $arrayObject2['food'] = 'Pizza';

        $this->assertEquals(2, count($arrayObject2));

        $this->assertEquals('Johnny Appleseed', $arrayObject2['name']);
        $this->assertEquals('Pizza', $arrayObject2['food']);

        $this->assertEquals($arrayObject1, $arrayObject2);
    }
}
