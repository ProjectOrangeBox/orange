<?php

declare(strict_types=1);

use orange\framework\Config;
use orange\framework\exceptions\config\ImmutableAccess;

final class ConfigTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $this->instance = Config::getInstance([
            'config separator' => '.',
            'config directories' => [
                WORKINGDIR . '/config',
                WORKINGDIR . '/config/testing',
            ]
        ]);
    }

    // Tests
    public function testGet(): void
    {
        $config = $this->instance->get('aaa');

        $this->assertEquals($config['color'], 'blue');
        $this->assertEquals($config['age'], 23);
        $this->assertEquals($config['size'], 'large');

        $config = $this->instance->get('bbb');

        $this->assertEquals($config['color'], 'green');
        $this->assertEquals($config['age'], 33);
        $this->assertEquals($config['size'], 'small');
    }

    public function testGetKey(): void
    {
        $this->assertEquals($this->instance->get('aaa.color'), 'blue');
        $this->assertEquals($this->instance->get('aaa.age'), '23');
        $this->assertEquals($this->instance->get('bbb.color'), 'green');
    }

    public function testM_M_Get(): void
    {
        $config = $this->instance->aaa;

        $this->assertEquals($config['color'], 'blue');
        $this->assertEquals($config['age'], 23);
        $this->assertEquals($config['size'], 'large');

        $config = $this->instance->bbb;

        $this->assertEquals($config['color'], 'green');
        $this->assertEquals($config['age'], 33);
        $this->assertEquals($config['size'], 'small');
    }

    public function testInvalidConfigFile(): void
    {
        $config = $this->instance->get('ccc');

        $this->assertEquals($config, []);
    }

    public function testOffsetGet(): void
    {
        $config = $this->instance['aaa'];

        $this->assertEquals('blue', $config['color']);
    }

    public function testOffsetExists(): void
    {
        $this->assertTrue(isset($this->instance['aaa']));
    }

    public function testOffsetExistsFalseForUnknownConfigDoesNotThrow(): void
    {
        // a filename with no discovered config files at all isn't in $foundConfigFiles;
        // count(null) is a TypeError under PHP 8, so this must not crash
        $this->assertFalse(isset($this->instance['thisConfigFileDoesNotExistAnywhere']));
    }

    public function testOffsetSetThrows(): void
    {
        $this->expectException(ImmutableAccess::class);

        $this->instance['aaa'] = ['color' => 'red'];
    }

    public function testOffsetUnsetThrows(): void
    {
        $this->expectException(ImmutableAccess::class);

        unset($this->instance['aaa']);
    }

    public function testGetWithDefaultReturnsDefaultForMissingKey(): void
    {
        $this->assertEquals('fallback', $this->instance->get('aaa.doesNotExist', 'fallback'));
    }

    public function testGetWithDefaultReturnsValueWhenKeyPresent(): void
    {
        $this->assertEquals('blue', $this->instance->get('aaa.color', 'fallback'));
    }

    public function testLoadThrowsWhenConfigFileDoesNotReturnArray(): void
    {
        $badFile = WORKINGDIR . '/config/notarrayconfig.php';
        file_put_contents($badFile, "<?php\nreturn 'not an array';\n");

        try {
            $config = Config::newInstance([
                'config separator' => '.',
                'config directories' => [WORKINGDIR . '/config'],
            ]);

            $this->expectException(\orange\framework\exceptions\config\ConfigFileDidNotReturnAnArray::class);
            $config->get('notarrayconfig');
        } finally {
            unlink($badFile);
        }
    }
}
