<?php

declare(strict_types=1);

use orange\framework\Data;
use orange\framework\View;
use orange\framework\Input;
use orange\framework\Router;
use orange\framework\exceptions\view\ViewNotFound;

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
}
