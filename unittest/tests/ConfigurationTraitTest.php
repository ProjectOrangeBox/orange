<?php

declare(strict_types=1);

use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\MissingRequired;
use orange\framework\exceptions\config\ConfigFileNotFound;

final class ConfigurationTraitTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        require_once MOCKDIR . '/configurationTraitMocks.php';

        $this->instance = new configurationTraitMock();
    }

    /* changeOption() */

    public function testChangeOptionSetsProperty(): void
    {
        $this->instance->changeOption('color', 'green');

        $this->assertEquals('green', $this->instance->color);
    }

    public function testChangeOptionReturnsSelfForChaining(): void
    {
        $returned = $this->instance->changeOption('color', 'green');

        $this->assertSame($this->instance, $returned);
    }

    public function testChangeOptionAcceptsHumanReadableName(): void
    {
        // 'via setter' camelizes to 'viaSetter' then calls setViaSetter()
        $this->instance->changeOption('via setter', 'hello');

        $this->assertEquals('hello', $this->instance->setterCalledWith);
        $this->assertEquals('hello', $this->getPrivatePublic('viaSetter'));
    }

    public function testChangeOptionPrefersSetterOverProperty(): void
    {
        $this->instance->changeOption('viaSetter', 'through the setter');

        $this->assertEquals('through the setter', $this->instance->setterCalledWith);
    }

    public function testChangeOptionChecksTypeWithFunction(): void
    {
        $this->expectException(InvalidValue::class);

        // color is checked with is_string
        $this->instance->changeOption('color', 123);
    }

    public function testChangeOptionChecksTypeWithInstanceOf(): void
    {
        $when = new \DateTime();

        $this->instance->changeOption('when', $when);

        $this->assertSame($when, $this->instance->when);
    }

    public function testChangeOptionInstanceOfMismatchThrows(): void
    {
        $this->expectException(InvalidValue::class);

        $this->instance->changeOption('when', new \DateTimeZone('UTC'));
    }

    public function testChangeOptionUnknownNameThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Cannot set notChangeable');

        $this->instance->changeOption('notChangeable', 'x');
    }

    public function testChangeOptionWithoutPropertyOrSetterThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('property or set method not found ghost');

        $this->instance->changeOption('ghost', 'x');
    }

    public function testChangeOptionWithoutTypeCheckPropertyThrows(): void
    {
        $this->expectException(MissingRequired::class);
        $this->expectExceptionMessage('Change not supported');

        (new configurationTraitNoTypeCheckMock())->changeOption('color', 'green');
    }

    public function testChangeOptionWithNonArrayTypeCheckThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('changeableTypeCheck is not an array.');

        (new configurationTraitBadTypeCheckMock())->changeOption('color', 'green');
    }

    /* mergeConfigWith() */

    public function testMergeConfigWithExplicitPath(): void
    {
        $merged = $this->callMethod('mergeConfigWith', [['color' => 'red'], MOCKDIR . '/config/configurationtraitmock.php']);

        // passed in value wins over the file value
        $this->assertEquals('red', $merged['color']);
        $this->assertEquals(['a' => 1, 'b' => 2], $merged['nested']);
    }

    public function testMergeConfigWithAutoDetectsPathFromClassName(): void
    {
        // no path -> lowercased short class name -> mocks/config/configurationtraitmock.php
        $merged = $this->callMethod('mergeConfigWith', [[]]);

        $this->assertEquals('blue', $merged['color']);
    }

    public function testMergeConfigWithRecursiveKeepsSiblingKeys(): void
    {
        $merged = $this->callMethod('mergeConfigWith', [['nested' => ['a' => 99]], null, true]);

        $this->assertEquals(['a' => 99, 'b' => 2], $merged['nested']);
    }

    public function testMergeConfigWithNonRecursiveReplacesWholeKey(): void
    {
        $merged = $this->callMethod('mergeConfigWith', [['nested' => ['a' => 99]], null, false]);

        // non recursive replaces the entire 'nested' value so 'b' is dropped
        $this->assertEquals(['a' => 99], $merged['nested']);
    }

    public function testMergeConfigWithBooleanAsPathIsTreatedAsRecursiveFlag(): void
    {
        $merged = $this->callMethod('mergeConfigWith', [['nested' => ['a' => 99]], false]);

        $this->assertEquals(['a' => 99], $merged['nested']);
    }

    public function testMergeConfigWithFileNotReturningArrayThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('did not return an array.');

        $this->callMethod('mergeConfigWith', [[], MOCKDIR . '/config/notanarray.php']);
    }

    /* getConfigFile() */

    public function testGetConfigFileMergesLikeMergeConfigWith(): void
    {
        $merged = $this->callMethod('getConfigFile', ['configurationtraitmock', ['color' => 'red']]);

        $this->assertEquals('red', $merged['color']);
        $this->assertEquals(['a' => 1, 'b' => 2], $merged['nested']);
    }

    /* determineConfigPath() */

    public function testDetermineConfigPathFindsFileByName(): void
    {
        $path = $this->callMethod('determineConfigPath', ['configurationtraitmock']);

        $this->assertEquals(MOCKDIR . '/config/configurationtraitmock.php', $path);
    }

    public function testDetermineConfigPathNotFoundThrows(): void
    {
        $this->expectException(ConfigFileNotFound::class);

        $this->callMethod('determineConfigPath', ['thisConfigDoesNotExist']);
    }

    /* setFromConfig() */

    public function testSetFromConfigCallsMatchingSetter(): void
    {
        $this->callMethod('setFromConfig', [['via setter' => 'from config']]);

        $this->assertEquals('from config', $this->instance->setterCalledWith);
    }

    public function testSetFromConfigIgnoresUnknownKeysByDefault(): void
    {
        $this->callMethod('setFromConfig', [['no such thing' => 'x']]);

        $this->assertEquals('', $this->instance->setterCalledWith);
    }

    public function testSetFromConfigThrowsOnUnknownKeyWhenRequested(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('method not found setNoSuchThing.');

        $this->callMethod('setFromConfig', [['no such thing' => 'x'], true]);
    }

    /* assignFromConfig() */

    public function testAssignFromConfigSetsMatchingProperty(): void
    {
        $this->callMethod('assignFromConfig', [['color' => 'purple', 'age' => 42]]);

        $this->assertEquals('purple', $this->instance->color);
        $this->assertEquals(42, $this->instance->age);
    }

    public function testAssignFromConfigCamelizesKeys(): void
    {
        $this->callMethod('assignFromConfig', [['via setter' => 'assigned directly']]);

        // assigns the property directly, the setter is never called
        $this->assertEquals('assigned directly', $this->getPrivatePublic('viaSetter'));
        $this->assertEquals('', $this->instance->setterCalledWith);
    }

    public function testAssignFromConfigIgnoresUnknownKeysByDefault(): void
    {
        $this->callMethod('assignFromConfig', [['nope' => 'x']]);

        $this->assertEquals('red', $this->instance->color);
    }

    public function testAssignFromConfigThrowsOnUnknownKeyWhenRequested(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('property not found nope.');

        $this->callMethod('assignFromConfig', [['nope' => 'x'], true]);
    }

    /* string helpers */

    public function testNormalize(): void
    {
        $this->assertEquals('foobar', $this->callMethod('normalize', ['FooBAR']));
    }

    public function testCamelize(): void
    {
        $this->assertEquals('fooBar', $this->callMethod('camelize', ['foo bar']));
        $this->assertEquals('fooBar', $this->callMethod('camelize', ['foo_bar']));
        $this->assertEquals('shippingCarrier', $this->callMethod('camelize', ['Shipping Carrier']));
    }

    public function testCamelizeUcFirst(): void
    {
        $this->assertEquals('FooBar', $this->callMethod('camelize', ['foo bar', true]));
        $this->assertEquals('ShippingCarrier', $this->callMethod('camelize', ['Shipping Carrier', true]));
    }

    public function testUnderscore(): void
    {
        $this->assertEquals('foo_bar', $this->callMethod('underscore', ['Foo Bar']));
    }

    public function testHumanize(): void
    {
        $this->assertEquals('Foo Bar', $this->callMethod('humanize', ['foo_bar']));
    }

    public function testHumanizeWithSeparator(): void
    {
        $this->assertEquals('Foo Bar', $this->callMethod('humanize', ['foo-bar', '-']));
    }

    /* validateConfig() */

    public function testValidateConfigPassingTypeRules(): void
    {
        $this->callMethod('validateConfig', [
            ['color' => 'blue', 'age' => 23, 'list' => [1, 2]],
            ['color' => 'string', 'age' => 'integer', 'list' => 'array'],
        ]);

        // no exception thrown
        $this->assertTrue(true);
    }

    public function testValidateConfigIntIsNormalizedToInteger(): void
    {
        $this->callMethod('validateConfig', [['age' => 23], ['age' => 'int']]);

        $this->assertTrue(true);
    }

    public function testValidateConfigIgnoresKeysWithoutRules(): void
    {
        $this->callMethod('validateConfig', [['unruled' => new stdClass()], ['color' => 'string']]);

        $this->assertTrue(true);
    }

    public function testValidateConfigTypeMismatchThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('age not an integer');

        $this->callMethod('validateConfig', [['age' => 'twenty three'], ['age' => 'integer']]);
    }

    public function testValidateConfigCollectsMultipleErrors(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('color not an string, age not an integer');

        $this->callMethod('validateConfig', [
            ['color' => 123, 'age' => 'x'],
            ['color' => 'string', 'age' => 'integer'],
        ]);
    }

    public function testValidateConfigMinRule(): void
    {
        $this->callMethod('validateConfig', [
            ['name' => 'abcd', 'age' => 20, 'list' => [1, 2, 3]],
            ['name' => 'min[3]', 'age' => 'min[18]', 'list' => 'min[2]'],
        ]);

        $this->assertTrue(true);
    }

    public function testValidateConfigMinRuleFailsOnShortString(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('name min is not 3');

        $this->callMethod('validateConfig', [['name' => 'ab'], ['name' => 'min[3]']]);
    }

    public function testValidateConfigMinRuleFailsOnSmallInteger(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('age min is not 18');

        $this->callMethod('validateConfig', [['age' => 17], ['age' => 'min[18]']]);
    }

    public function testValidateConfigMinRuleOnUnsupportedTypeThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('can not use min on double');

        $this->callMethod('validateConfig', [['ratio' => 1.5], ['ratio' => 'min[1]']]);
    }

    public function testValidateConfigMaxRuleFailsOnLongString(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('name max is not 3');

        $this->callMethod('validateConfig', [['name' => 'abcd'], ['name' => 'max[3]']]);
    }

    public function testValidateConfigMaxRuleFailsOnLargeArray(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('list max is not 2');

        $this->callMethod('validateConfig', [['list' => [1, 2, 3]], ['list' => 'max[2]']]);
    }

    public function testValidateConfigCountRule(): void
    {
        // NOTE: previously inverted - the valid (matching count) case raised "can not use
        // count on X" while the mismatch case was the only one that stayed silent, so
        // this rule always threw regardless of the actual count. Fixed.
        $this->callMethod('validateConfig', [['list' => [1, 2, 3]], ['list' => 'count[3]']]);

        $this->assertTrue(true);
    }

    public function testValidateConfigCountRuleMismatchThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('list count is not 2');

        $this->callMethod('validateConfig', [['list' => [1, 2, 3]], ['list' => 'count[2]']]);
    }

    public function testValidateConfigCountRuleOnNonArrayThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('can not use count on string');

        $this->callMethod('validateConfig', [['name' => 'abcd'], ['name' => 'count[3]']]);
    }

    public function testValidateConfigSizeRule(): void
    {
        $this->callMethod('validateConfig', [
            ['name' => 'abc', 'list' => [1, 2]],
            ['name' => 'size[3]', 'list' => 'size[2]'],
        ]);

        $this->assertTrue(true);
    }

    public function testValidateConfigSizeRuleMismatchThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('name size does not match 3');

        $this->callMethod('validateConfig', [['name' => 'abcd'], ['name' => 'size[3]']]);
    }

    public function testValidateConfigClassRule(): void
    {
        $this->callMethod('validateConfig', [['when' => new \DateTime()], ['when' => 'class[DateTime]']]);

        $this->assertTrue(true);
    }

    public function testValidateConfigClassRuleMismatchThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('when is not an instance of DateTime');

        $this->callMethod('validateConfig', [['when' => new stdClass()], ['when' => 'class[DateTime]']]);
    }

    public function testValidateConfigChainedRules(): void
    {
        $this->callMethod('validateConfig', [['name' => 'abcd'], ['name' => 'string,min[2],max[10]']]);

        $this->assertTrue(true);
    }

    public function testValidateConfigUnknownRuleThrows(): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessage('Unknown validate config rule bogus');

        $this->callMethod('validateConfig', [['color' => 'blue'], ['color' => 'bogus']]);
    }
}
