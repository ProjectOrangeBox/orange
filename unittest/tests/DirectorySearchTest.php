<?php

declare(strict_types=1);

use orange\framework\helpers\DirectorySearch;
use orange\framework\interfaces\DirectorySearchInterface;

final class DirectorySearchTest extends UnitTestHelper
{
    protected $instance = null;

    protected $d1 = WORKINGDIR . '/directorySearch';
    protected $d2 = WORKINGDIR . '/directorySearch/bar';
    protected $d3 = WORKINGDIR . '/directorySearch/bbb';
    protected $d4 = WORKINGDIR . '/directorySearch/ccc';
    protected $d5 = WORKINGDIR . '/directorySearch/foo';
    protected $d6 = WORKINGDIR . '/directorySearch/aaa';

    protected $r1 = WORKINGDIR . '/directorySearch/bar/bar.php';
    protected $r2 = WORKINGDIR . '/directorySearch/bar/foo.php';
    protected $r3 = WORKINGDIR . '/directorySearch/bar/aaa/bar.php';
    protected $r4 = WORKINGDIR . '/directorySearch/bar/aaa/foo.php';
    protected $r5 = WORKINGDIR . '/directorySearch/bar/bbb/bar.php';
    protected $r6 = WORKINGDIR . '/directorySearch/bar/bbb/foo.php';

    protected function setUp(): void
    {
        if (!isset($this->instance)) {
            $this->instance = new DirectorySearch([
                'match' => '*.php',
                'quiet' => true,
                'lock after scan' => false,
                'recursive' => true,
                'normalize keys' => true,
                'locked' => false,
                'pend' => DirectorySearchInterface::PREPEND,
                'callback' => [],
            ]);
        }
        $this->instance->flushDirectories()->flushResources();
    }

    public function testAddDirectoryWhenLockedThrows(): void
    {
        $this->instance->lock();

        $this->expectException(\orange\framework\exceptions\ClassLocked::class);
        $this->instance->addDirectory($this->d1);
    }

    public function testFindMissingResourceThrowsWhenNotQuiet(): void
    {
        $this->setPrivatePublic('quiet', false);
        $this->instance->addDirectory($this->d2);

        $this->expectException(\orange\framework\exceptions\ResourceNotFound::class);
        $this->instance->find('no-such-resource');
    }

    public function testAddMissingDirectoryThrowsWhenNotQuiet(): void
    {
        $this->setPrivatePublic('quiet', false);

        $this->expectException(\orange\framework\exceptions\filesystem\DirectoryNotFound::class);
        $this->instance->addDirectory('/does/not/exist/anywhere');
    }

    public function testDirectoryExists(): void
    {
        $this->instance->addDirectory($this->d1);

        $this->assertTrue($this->instance->directoryExists($this->d1));
        $this->assertFalse($this->instance->directoryExists('/no/such/dir'));
    }

    public function testExistsFindsResource(): void
    {
        $this->instance->addDirectory($this->d2);

        $this->assertTrue($this->instance->exists('bar'));
        $this->assertFalse($this->instance->exists('does-not-exist'));
    }

    public function testLockUnlockIsLocked(): void
    {
        $this->assertFalse($this->instance->isLocked());

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->lock());
        $this->assertTrue($this->instance->isLocked());

        $this->instance->unlock();
        $this->assertFalse($this->instance->isLocked());
    }

    public function testDebugInfoReportsResourcesAndDirectories(): void
    {
        $this->instance->addDirectory($this->d2);

        $debug = $this->instance->__debugInfo();

        $this->assertArrayHasKey('resources', $debug);
        $this->assertArrayHasKey('directories', $debug);
    }

    public function testAddDirectory(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectory($this->d1));
        $this->assertEquals([$this->d1], $this->instance->listDirectories());

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectory($this->d2));
        $this->assertEquals([$this->d2, $this->d1], $this->instance->listDirectories());

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectory($this->d3, DirectorySearchInterface::APPEND));
        $this->assertEquals([$this->d2, $this->d1, $this->d3], $this->instance->listDirectories());
    }

    public function testAddDirectories(): void
    {
        $directories = [$this->d1, $this->d2];

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectories($directories));
        $this->assertEquals($directories, $this->instance->listDirectories());

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectory($this->d3));
        $this->assertEquals([$this->d3, $this->d1, $this->d2], $this->instance->listDirectories());
    }

    public function testRemoveDirectory(): void
    {
        $directories = [$this->d2, $this->d3, $this->d4];

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectories($directories));
        $this->assertEquals($directories, $this->instance->listDirectories());
        $this->assertTrue($this->instance->directoryExists($this->d3));

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->removeDirectory($this->d2));
        $this->assertEquals([$this->d3, $this->d4], $this->instance->listDirectories());
    }

    public function testRemoveDirectories(): void
    {
        $directories = [$this->d2, $this->d3, $this->d4];

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectories($directories));
        $this->assertEquals($directories, $this->instance->listDirectories());

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->removeDirectories([$this->d3, $this->d4]));
        $this->assertEquals([$this->d2], $this->instance->listDirectories());
    }

    public function testReplaceDirectories(): void
    {
        $directories1 = [$this->d1, $this->d2];
        $directories2 = [$this->d3, $this->d4];

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectories($directories1));
        $this->assertEquals($directories1, $this->instance->listDirectories());

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->replaceDirectories($directories2));
        $this->assertEquals($directories2, $this->instance->listDirectories());
    }

    /* resources */

    public function testAddResource(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addResource('bar', $this->r1));
        $this->assertTrue($this->instance->exists('bar'));
        $this->assertFalse($this->instance->exists('foo'));
    }

    public function testAddResources(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addResources(['nope.html' => '/bogus/nope.html', 'bar.html' => $this->r1, 'foo.html' => $this->r2]));
        $this->assertTrue($this->instance->exists('bar.html'));
        $this->assertTrue($this->instance->exists('foo.html'));
        $this->assertFalse($this->instance->exists('nope.html'));
    }

    public function testReplaceResources(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addResources(['nope.html' => '/bogus/nope.html', 'bar.html' => $this->r1, 'foo.html' => $this->r2]));
        $this->assertTrue($this->instance->exists('bar.html'));
        $this->assertTrue($this->instance->exists('foo.html'));
        $this->assertFalse($this->instance->exists('nope.html'));

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->replaceResources(['nope' => '/bogus/nope.html', 'aaa' => $this->r1, 'bbb' => $this->r2]));
        $this->assertTrue($this->instance->exists('aaa'));
        $this->assertTrue($this->instance->exists('bbb'));
        $this->assertFalse($this->instance->exists('nope'));
    }

    public function testFindAll(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectory(WORKINGDIR . '/directorySearch/config'));

        $matches = [
            'app' => [0 => WORKINGDIR . '/directorySearch/config/app.php'],
            'dev/app' => [0 => WORKINGDIR . '/directorySearch/config/dev/app.php'],
        ];

        $this->assertEquals($matches, $this->instance->findAll());
    }

    public function testFindFirst(): void
    {
        $a = WORKINGDIR . '';
        $b = WORKINGDIR . '/env';

        $this->instance = new DirectorySearch([
            'quiet' => true,
            'lock after scan' => false,
            'recursive' => true,
            'extract resource key' => function ($fileInfo) {
                return $fileInfo['filename'];
            }
        ]);

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectories([$a, $b]));
        $this->assertEquals(WORKINGDIR . '/configExample2.php', $this->instance->findFirst('configExample2'));
    }

    public function testFindLast(): void
    {
        $a = WORKINGDIR . '';
        $b = WORKINGDIR . '/env';

        $this->instance = new DirectorySearch([
            'quiet' => true,
            'lock after scan' => false,
            'recursive' => true,
            'extract resource key' => function ($fileInfo) {
                return $fileInfo['filename'];
            }
        ]);

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectories([$a, $b]));

        $this->assertEquals(WORKINGDIR . '/env/configExample2.php', $this->instance->findLast('configExample2'));
    }

    public function testFlushDirectories(): void
    {
        $directories = [$this->d1, $this->d2];

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectories($directories));
        $this->assertEquals($directories, $this->instance->listDirectories());

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->flushDirectories());
        $this->assertEquals([], $this->instance->listDirectories());
    }

    public function testFlushResources(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addResource('bar', $this->r1));
        $this->assertTrue($this->instance->exists('bar'));

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->flushResources());
        $this->assertFalse($this->instance->exists('bar'));
    }

    public function testRemoveResource(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addResource('bar', $this->r1));
        $this->assertTrue($this->instance->exists('bar'));

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->removeResource('bar'));
        $this->assertFalse($this->instance->exists('bar'));
    }

    public function testRemoveResources(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addResources(['bar' => $this->r1, 'foo' => $this->r2]));
        $this->assertTrue($this->instance->exists('bar'));
        $this->assertTrue($this->instance->exists('foo'));

        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->removeResources(['bar', 'foo']));
        $this->assertFalse($this->instance->exists('bar'));
        $this->assertFalse($this->instance->exists('foo'));
    }

    public function testFind(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectory($this->d1));

        $result = $this->instance->find('bar/bar');
        $this->assertIsArray($result);
        $this->assertContains($this->r1, $result);
    }

    public function testList(): void
    {
        $this->assertInstanceOf(DirectorySearchInterface::class, $this->instance->addDirectory($this->d1));

        $result = $this->instance->list();
        $this->assertIsArray($result);
        $this->assertContains('bar/bar', $result);
    }

    public function testRemoveDirectoryAlsoRemovesMatchingResources(): void
    {
        $this->instance->addDirectory($this->d2);

        // force a scan so $this->resources actually gets populated with paths
        // under d2, giving removeDirectory() something to match against
        $this->instance->list();
        $this->assertNotEmpty($this->instance->__debugInfo()['resources']);

        $this->instance->removeDirectory($this->d2, true);

        $this->assertEquals([], $this->instance->__debugInfo()['resources']);
    }

    public function testScanDirectoriesNonRecursiveUsesGlobAndLocksAfterScan(): void
    {
        $instance = new DirectorySearch([
            'match' => '*.php',
            'quiet' => true,
            'recursive' => false,
            'lock after scan' => true,
        ]);

        $this->assertFalse($instance->isLocked());

        $instance->addDirectory($this->d2);

        // non-recursive glob() only sees files directly in d2, not aaa/bbb subdirs
        $this->assertTrue($instance->exists('bar'));
        $this->assertFalse($instance->exists('aaa/bar'));

        // 'lock after scan' locks the instance the first time scanDirectories() runs
        $this->assertTrue($instance->isLocked());
    }

    public function testCallbackInvokesRegisteredCallback(): void
    {
        $recorder = new class {
            public array $calls = [];
            public function record($args): void
            {
                $this->calls[] = $args[0];
            }
        };

        $instance = new DirectorySearch([
            'quiet' => true,
            'callback' => [$recorder, 'record'],
        ]);

        $instance->addDirectory($this->d1);

        $this->assertContains('addDirectory', $recorder->calls);
    }

    public function testCallbackThrowsNotFoundWhenMethodMissing(): void
    {
        $recorder = new class {
        };

        // the constructor itself calls flushDirectories() -> callback(), so the
        // bad callback throws during construction, before addDirectory() runs
        $this->expectException(\orange\framework\exceptions\NotFound::class);

        new DirectorySearch([
            'quiet' => true,
            'callback' => [$recorder, 'noSuchMethod'],
        ]);
    }

    /* resource key styles */

    public function testResourceKeyStyleFilename(): void
    {
        $instance = new DirectorySearch([
            'match' => 'bar.php',
            'quiet' => true,
            'recursive' => false,
            'resource key style' => 'filename',
        ]);
        $instance->addDirectory($this->d2);

        $this->assertEquals(['bar'], $instance->list());
        $this->assertEquals($this->r1, $instance->findFirst('bar'));
    }

    public function testResourceKeyStyleBasename(): void
    {
        $instance = new DirectorySearch([
            'match' => 'bar.php',
            'quiet' => true,
            'recursive' => false,
            'resource key style' => 'basename',
        ]);
        $instance->addDirectory($this->d2);

        $this->assertEquals(['bar.php'], $instance->list());
    }

    public function testResourceKeyStyleFullpath(): void
    {
        $instance = new DirectorySearch([
            'match' => 'bar.php',
            'quiet' => true,
            'recursive' => false,
            'normalize keys' => false,
            'resource key style' => 'fullpath',
        ]);
        $instance->addDirectory($this->d2);

        $this->assertEquals([$this->r1], $instance->list());
    }

    public function testResourceKeyStyleLocalpath(): void
    {
        $instance = new DirectorySearch([
            'match' => 'bar.php',
            'quiet' => true,
            'recursive' => false,
            'resource key style' => 'localpath',
        ]);
        $instance->addDirectory($this->d2);

        $this->assertEquals(['bar.php'], $instance->list());
    }

    public function testResourceKeyStyleApppath(): void
    {
        $instance = new DirectorySearch([
            'match' => 'bar.php',
            'quiet' => true,
            'recursive' => false,
            'normalize keys' => false,
            'resource key style' => 'apppath',
        ]);
        $instance->addDirectory($this->d2);

        $expected = substr($this->r1, strlen(__ROOT__));
        $this->assertEquals([$expected], $instance->list());
    }

    // 'wwwpath' style (which needs a valid __WWW__ constant, i.e. a real htdocs
    // directory under __ROOT__) isn't exercised: this checkout's __ROOT__ has no
    // htdocs directory, so __WWW__ is false in every test process here, and the
    // style's closure (DirectorySearch.php:751) would crash calling strlen(false).
}
