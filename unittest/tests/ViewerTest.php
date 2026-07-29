<?php

declare(strict_types=1);

use orange\framework\Data;
use orange\framework\View;
use orange\framework\exceptions\view\ViewNotFound;
use orange\framework\exceptions\filesystem\Directory;
use orange\framework\exceptions\filesystem\FileNotWritable;
use orange\framework\exceptions\filesystem\DirectoryNotWritable;

final class ViewerTest extends unitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $config = [
            'temp directory' => sys_get_temp_dir(),
            'debug' => false,
        ];

        // no view paths and no router: the engine neither finds a view nor
        // works out its name. BaseController::renderView() resolves $c/$m and
        // ViewFinder turns the result into the path handed to render()
        $this->instance = View::getInstance($config, Data::getInstance([]));
    }

    // Tests
    public function testRender(): void
    {
        $this->assertEquals('<h1>Hello World</h1>', $this->instance->render(WORKINGDIR . '/views/test.php', ['hello' => 'Hello World']));
    }

    public function testRenderString(): void
    {
        $this->assertEquals('<h1>Hello World</h1>', $this->instance->renderString('<h1><?=$hello ?></h1>', ['hello' => 'Hello World']));
    }

    public function testRenderStringCacheDirectoryIsNotGroupOrWorldWritable(): void
    {
        // regression guard: this directory holds compiled string-templates that get
        // require()'d as executable PHP - it must never come out world/group-writable,
        // which mkdir($dir, 0777, true) used to risk on a permissive umask
        $string = '<h1>unique-' . uniqid() . '</h1>';

        $this->instance->renderString($string);

        $subPath = substr(sha1($string, false), 0, $this->getPrivatePublic('subPathSize'));
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $subPath;

        $this->assertDirectoryExists($dir);
        $this->assertEquals(0, fileperms($dir) & 0022);
    }

    public function testRenderViewNotFoundException(): void
    {
        $this->expectException(ViewNotFound::class);

        $this->assertNull($this->instance->render(WORKINGDIR . '/views/dummy.php'));
    }

    public function testChangeOption(): void
    {
        $this->instance->changeOption('debug', false);
        $this->assertFalse($this->getPrivatePublic('debug'));

        $this->instance->changeOption('debug', true);
        $this->assertTrue($this->getPrivatePublic('debug'));
    }

    /**
     * render() takes a path, not a name. 'test' is a real view under
     * WORKINGDIR/views and used to render from that bare name; now nothing
     * interprets it - not a search path, not an alias - so it is simply a file
     * that is not there.
     */
    public function testDoesNotInterpretAViewName(): void
    {
        $this->expectException(ViewNotFound::class);

        $this->instance->render('test', ['hello' => 'Hello World']);
    }

    public function testChangeValidOption(): void
    {
        $this->instance->change('debug', true);
        $this->assertTrue($this->getPrivatePublic('debug'));

        $this->instance->change('debug', false);
        $this->assertFalse($this->getPrivatePublic('debug'));
    }

    public function testChangeReturnsSelf(): void
    {
        $this->assertSame($this->instance, $this->instance->change('debug', true));
    }

    public function testChangeUnknownOptionThrows(): void
    {
        $this->expectException(\orange\framework\exceptions\InvalidValue::class);

        $this->instance->change('notAnOption', 'x');
    }

    public function testChangeWrongTypeThrows(): void
    {
        $this->expectException(\orange\framework\exceptions\InvalidValue::class);

        // debug expects is_bool
        $this->instance->change('debug', 'not a bool');
    }

    public function testChangeWrongTypeWithArrayValueDoesNotWarn(): void
    {
        // regression guard: the exception message used to concatenate $value directly,
        // which triggers "Array to string conversion" for an array (or a fatal error for
        // a non-Stringable object) instead of cleanly reporting the mismatch
        $this->expectException(\orange\framework\exceptions\InvalidValue::class);
        $this->expectExceptionMessage('array is not is_bool');

        $this->instance->change('debug', ['not', 'a', 'bool']);
    }

    public function testRenderMergesViewLevelData(): void
    {
        // the Data store passed to the view in setUp is a singleton; seed it
        Data::getInstance([])->merge(['hello' => 'From View Data']);

        // no data passed to render(), so it must come from the merged view data
        $this->assertEquals('<h1>From View Data</h1>', $this->instance->render(WORKINGDIR . '/views/test.php'));
    }

    /* constructor */

    public function testConstructorThrowsWhenTempDirectoryMissing(): void
    {
        $this->expectException(Directory::class);

        View::newInstance([
            'temp directory' => WORKINGDIR . '/definitely/not/a/real/directory/xyz',
            'debug' => false,
        ], Data::getInstance([]));
    }

    /* generate() */

    public function testGenerateThrowsViewNotFoundForMissingFile(): void
    {
        $this->expectException(ViewNotFound::class);

        $this->callMethod('generate', [WORKINGDIR . '/views/does-not-exist.php', []]);
    }

    /* isFileWritable() */

    public function testIsFileWritableCreatesMissingDirectory(): void
    {
        $newDir = WORKINGDIR . '/viewdircreated';

        if (is_dir($newDir)) {
            rmdir($newDir);
        }

        try {
            $this->assertTrue($this->callMethod('isFileWritable', [$newDir . '/view.php']));
            $this->assertDirectoryExists($newDir);
        } finally {
            if (is_dir($newDir)) {
                rmdir($newDir);
            }
        }
    }

    public function testIsFileWritableThrowsWhenDirectoryCreationFails(): void
    {
        $blockerFile = WORKINGDIR . '/viewblocker';
        file_put_contents($blockerFile, '');

        set_error_handler(function ($errno, $errstr) {
            throw new \ErrorException($errstr);
        });

        try {
            $this->expectException(DirectoryNotWritable::class);

            $this->callMethod('isFileWritable', [$blockerFile . '/subdir/view.php']);
        } finally {
            restore_error_handler();
            unlink($blockerFile);
        }
    }

    public function testIsFileWritableThrowsWhenDirectoryNotWritable(): void
    {
        $readOnlyDir = WORKINGDIR . '/viewreadonlydir';

        if (!is_dir($readOnlyDir)) {
            mkdir($readOnlyDir);
        }
        chmod($readOnlyDir, 0555);

        try {
            $this->expectException(FileNotWritable::class);

            $this->callMethod('isFileWritable', [$readOnlyDir . '/view.php']);
        } finally {
            chmod($readOnlyDir, 0755);
            rmdir($readOnlyDir);
        }
    }

    /* renderString() atomic write failure */

    public function testRenderStringThrowsFileNotWritableWhenAtomicWriteFails(): void
    {
        $string = '<h1>atomic-fail-' . uniqid() . '</h1>';
        $filename = sha1($string, false);
        $subPathSize = $this->getPrivatePublic('subPathSize');
        $tempDirectory = $this->getPrivatePublic('tempDirectory');
        $subPath = $subPathSize > 0 ? DIRECTORY_SEPARATOR . substr($filename, 0, $subPathSize) : '';
        $templatePath = $tempDirectory . $subPath . DIRECTORY_SEPARATOR . $filename . '.php';

        // pre-create the exact target path as a directory so the atomic rename()
        // inside file_put_contents_atomic() fails (renaming a file onto an
        // existing directory), forcing renderString() down its write-failure path
        mkdir($templatePath, 0755, true);

        $this->instance->change('debug', true);

        try {
            $this->expectException(FileNotWritable::class);

            // the underlying rename() is expected to fail here (that's the point of
            // the test) and emits a PHP warning we don't care about; suppress it so
            // the test output stays focused on the exception being asserted
            @$this->instance->renderString($string);
        } finally {
            rmdir($templatePath);
            $this->instance->change('debug', false);
        }
    }
}
