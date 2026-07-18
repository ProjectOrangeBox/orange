<?php

declare(strict_types=1);

use orange\framework\Data;
use orange\framework\View;
use orange\framework\Input;
use orange\framework\Router;
use orange\framework\exceptions\view\ViewNotFound;
use orange\framework\exceptions\filesystem\Directory;
use orange\framework\exceptions\filesystem\FileNotWritable;
use orange\framework\exceptions\filesystem\DirectoryNotWritable;

final class ViewerTest extends UnitTestHelper
{
    protected $instance;

    protected function setUp(): void
    {
        $config = [
            'view paths' => [
                WORKINGDIR . '/views',
            ],
            'view aliases' => [],
            'temp directory' => sys_get_temp_dir(),
            'debug' => false,
            'match' => '*.php',
        ];

        $this->instance = View::getInstance(
            $config,
            Data::getInstance([]),
            Router::getInstance(['site url' => 'www.example.com'], Input::getInstance([
                'force https' => false,
            ])),
        );
    }

    // Tests
    public function testRender(): void
    {
        $this->assertEquals('<h1>Hello World</h1>', $this->instance->render('test', ['hello' => 'Hello World']));
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

    public function testAddPath(): void
    {
        // path added above let's test for it.
        $this->assertTrue(in_array(WORKINGDIR . '/views', $this->instance->search->listDirectories()));
    }

    public function testAddPaths(): void
    {
        $this->instance->search->addDirectories([
            WORKINGDIR . '/directorySearch/foo',
            WORKINGDIR . '/directorySearch/bar'
        ]);

        $this->assertTrue(in_array(WORKINGDIR . '/directorySearch/foo', $this->instance->search->listDirectories()));
        $this->assertTrue(in_array(WORKINGDIR . '/directorySearch/bar', $this->instance->search->listDirectories()));
    }

    public function testRenderViewNotFoundException(): void
    {
        $this->expectException(ViewNotFound::class);

        $this->assertNull($this->instance->render('dummy'));
    }

    public function testChangeOption(): void
    {
        $this->instance->changeOption('debug', false);
        $this->assertFalse($this->getPrivatePublic('debug'));

        $this->instance->changeOption('debug', true);
        $this->assertTrue($this->getPrivatePublic('debug'));
    }

    public function testRecursive(): void
    {
        $this->instance->search->flushDirectories(true)->addDirectory(WORKINGDIR . '/views/errors');
        $this->assertEquals(WORKINGDIR . '/views/errors/cli/404.php', $this->instance->search->findFirst('cli/404'));
    }

    public function testSearchMethodReturnsDirectorySearch(): void
    {
        $this->assertInstanceOf(
            \orange\framework\interfaces\DirectorySearchInterface::class,
            $this->instance->search()
        );
    }

    public function testAddAliasResolvesOnRender(): void
    {
        $this->instance->addAlias('greeting', 'test');

        // rendering the alias resolves to the real 'test' view
        $this->assertEquals('<h1>Hello World</h1>', $this->instance->render('greeting', ['hello' => 'Hello World']));
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
        $this->assertEquals('<h1>From View Data</h1>', $this->instance->render('test'));
    }

    /* resolveDynamicView() */

    private function useCallback(array $callback): void
    {
        $router = $this->createMock(\orange\framework\interfaces\RouterInterface::class);
        $router->method('getMatched')->willReturn($callback);

        $this->setPrivatePublic('router', $router);
        $this->setPrivatePublic('allowDynamicViews', true);
    }

    public function testResolveDynamicViewEmptyUsesControllerAndMethod(): void
    {
        $this->useCallback(['HomeController', 'index']);

        // '' -> '$c/$m' -> 'home/index' (controller suffix stripped, lowercased)
        $this->assertEquals('home/index', $this->callMethod('resolveDynamicView', ['']));
    }

    public function testResolveDynamicViewReplacesPlaceholders(): void
    {
        $this->useCallback(['HomeController', 'index']);

        $this->assertEquals('home/index', $this->callMethod('resolveDynamicView', ['$c/$m']));
    }

    public function testResolveDynamicViewReplacesMethodOnly(): void
    {
        $this->useCallback(['HomeController', 'edit']);

        $this->assertEquals('custom/edit', $this->callMethod('resolveDynamicView', ['custom/$m']));
    }

    public function testResolveDynamicViewEndsWithSlashStar(): void
    {
        $this->useCallback(['HomeController', 'save']);

        // 'foo/*' -> 'foo/$m' -> 'foo/save'
        $this->assertEquals('foo/save', $this->callMethod('resolveDynamicView', ['foo/*']));
    }

    public function testResolveDynamicViewNamespacedControllerSegments(): void
    {
        $this->useCallback(['App\\Admin\\UserController', 'edit']);

        // $1=app, $2=admin, $m=edit
        $this->assertEquals('app/admin/edit', $this->callMethod('resolveDynamicView', ['$1/$2/$m']));
    }

    public function testResolveDynamicViewMissingMethodThrows(): void
    {
        $this->useCallback(['HomeController', null]);

        $this->expectException(\orange\framework\exceptions\InvalidValue::class);
        $this->callMethod('resolveDynamicView', ['$m']);
    }

    public function testResolveDynamicViewMissingControllerThrows(): void
    {
        $this->useCallback([null, 'index']);

        $this->expectException(\orange\framework\exceptions\InvalidValue::class);
        $this->callMethod('resolveDynamicView', ['$c']);
    }

    public function testResolveDynamicViewEndsWithStarStar(): void
    {
        $this->useCallback(['HomeController', 'index']);

        // 'foo/*/*' -> 'foo/$c/$m' -> 'foo/home/index'
        $this->assertEquals('foo/home/index', $this->callMethod('resolveDynamicView', ['foo/*/*']));
    }

    /* constructor */

    public function testConstructorThrowsWhenTempDirectoryMissing(): void
    {
        $this->expectException(Directory::class);

        View::newInstance([
            'view paths' => [WORKINGDIR . '/views'],
            'view aliases' => [],
            'temp directory' => WORKINGDIR . '/definitely/not/a/real/directory/xyz',
            'debug' => false,
        ], Data::getInstance([]));
    }

    public function testConstructorAddsResources(): void
    {
        $instance = View::newInstance([
            'view paths' => [WORKINGDIR . '/views'],
            'view aliases' => [],
            'temp directory' => sys_get_temp_dir(),
            'debug' => false,
            'resources' => ['extraViews' => WORKINGDIR . '/views'],
        ], Data::getInstance([]));

        $this->assertTrue(in_array(WORKINGDIR . '/views', $instance->search->listDirectories()));
    }

    /* render() dynamic view resolution */

    public function testRenderResolvesDynamicViewWhenEnabledWithRouter(): void
    {
        $router = $this->createMock(\orange\framework\interfaces\RouterInterface::class);
        $router->method('getMatched')->willReturn(['TestController', 'render']);

        $this->setPrivatePublic('router', $router);
        $this->instance->change('allowDynamicViews', true);

        // '$c' -> 'test' (controller suffix stripped, lowercased), which matches
        // the existing working/views/test.php fixture used by testRender()
        $this->assertEquals('<h1>Hello World</h1>', $this->instance->render('$c', ['hello' => 'Hello World']));
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
