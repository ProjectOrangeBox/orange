<?php

declare(strict_types=1);

use orange\framework\helpers\DirectorySearch;
use orange\framework\interfaces\DirectorySearchInterface;

final class DirectorySearchTest extends unitTestHelper
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

    /* directory priority ordering */

    /**
     * Build three directories that each hold a same-named file, so the order
     * find() returns them in is entirely a question of directory priority.
     *
     * @return array{0: string, 1: array<string, string>}
     */
    protected function makePriorityTree(): array
    {
        $root = $this->makeTempDir('orange-priority-');
        $paths = [];

        foreach (['a', 'b', 'c'] as $name) {
            mkdir($root . '/' . $name);
            $file = $root . '/' . $name . '/shared.php';
            file_put_contents($file, '<?php // ' . $name);

            // DirectorySearch stores realpath()ed paths, and on macOS the temp
            // directory is reached through a symlink (/var -> /private/var), so the
            // expectations have to be resolved the same way to compare equal
            $paths[$name] = realpath($file);
        }

        return [$root, $paths];
    }

    protected function priorityInstance(array $directories): DirectorySearch
    {
        return new DirectorySearch([
            'quiet' => true,
            'recursive' => true,
            'resource key style' => 'filename',
            'directories' => $directories,
        ]);
    }

    /**
     * A directory added with PREPEND after an earlier scan has to win. This used
     * to fail: resources are never cleared between scans and re-assigning an
     * existing key does not move it, so paths stayed frozen in discovery order
     * and findFirst() kept returning whichever directory was scanned first.
     */
    public function testPrependAfterAScanTakesPriority(): void
    {
        [$root, $paths] = $this->makePriorityTree();

        $instance = $this->priorityInstance([$root . '/a']);

        // force the first scan so 'a' is already discovered
        $this->assertEquals($paths['a'], $instance->findFirst('shared'));

        $instance->addDirectory($root . '/b', DirectorySearchInterface::PREPEND);
        $this->assertEquals($paths['b'], $instance->findFirst('shared'));

        $instance->addDirectory($root . '/c', DirectorySearchInterface::PREPEND);
        $this->assertEquals($paths['c'], $instance->findFirst('shared'));

        // find() reports every match, highest priority first
        $this->assertEquals([$paths['c'], $paths['b'], $paths['a']], $instance->find('shared'));

        // and findLast() is the other end of that same list
        $this->assertEquals($paths['a'], $instance->findLast('shared'));

        $this->removeTempDir($root);
    }

    /**
     * APPEND is the mirror image - a directory added last stays last.
     */
    public function testAppendAfterAScanKeepsLowestPriority(): void
    {
        [$root, $paths] = $this->makePriorityTree();

        $instance = $this->priorityInstance([$root . '/a']);

        $this->assertEquals($paths['a'], $instance->findFirst('shared'));

        $instance->addDirectory($root . '/b', DirectorySearchInterface::APPEND);

        $this->assertEquals($paths['a'], $instance->findFirst('shared'));
        $this->assertEquals([$paths['a'], $paths['b']], $instance->find('shared'));

        $this->removeTempDir($root);
    }

    /**
     * Re-adding an already-scanned directory as PREPEND only reorders the
     * priority list - nothing new is found - so the ordering still has to move.
     */
    public function testRePrependingAnAlreadyScannedDirectoryReorders(): void
    {
        [$root, $paths] = $this->makePriorityTree();

        $instance = $this->priorityInstance([$root . '/a', $root . '/b']);

        $this->assertEquals([$paths['a'], $paths['b']], $instance->find('shared'));

        $instance->addDirectory($root . '/b', DirectorySearchInterface::PREPEND);

        $this->assertEquals([$paths['b'], $paths['a']], $instance->find('shared'));
        $this->assertEquals($paths['b'], $instance->findFirst('shared'));

        $this->removeTempDir($root);
    }

    /**
     * A path registered straight through addResource() belongs to no registered
     * directory, so it ranks behind anything a scan turned up rather than
     * landing wherever insertion order happened to put it.
     */
    public function testManuallyAddedResourceRanksBehindScannedOnes(): void
    {
        [$root, $paths] = $this->makePriorityTree();

        $instance = $this->priorityInstance([$root . '/a']);

        $instance->addResource('shared', $paths['c']);
        $instance->addDirectory($root . '/b', DirectorySearchInterface::PREPEND);

        $this->assertEquals([$paths['b'], $paths['a'], $paths['c']], $instance->find('shared'));

        $this->removeTempDir($root);
    }

    /* pruning */

    /**
     * A tree whose matching files sit in three places: the root, a normal
     * subdirectory, and a directory that pruning is supposed to skip entirely.
     *
     * @return string
     */
    protected function makePruneTree(): string
    {
        $root = $this->makeTempDir('orange-prune-');

        file_put_contents($root . '/top.php', '<?php');

        foreach (['sub', '.git', 'node_modules'] as $name) {
            mkdir($root . '/' . $name . '/deeper', 0777, true);

            // one at the pruned directory's top level and one below it, so a
            // prune that only skipped the directory itself would still show up
            file_put_contents($root . '/' . $name . '/in' . ltrim($name, '.') . '.php', '<?php');
            file_put_contents($root . '/' . $name . '/deeper/under' . ltrim($name, '.') . '.php', '<?php');
        }

        return $root;
    }

    protected function pruneInstance(string $root, array $config = []): DirectorySearch
    {
        return new DirectorySearch(array_replace([
            'quiet' => true,
            'recursive' => true,
            'match' => '*.php',
            'resource key style' => 'filename',
            'directories' => [$root],
        ], $config));
    }

    public function testDefaultPruneSkipsToolDirectoriesEntirely(): void
    {
        $root = $this->makePruneTree();

        $found = $this->pruneInstance($root)->list();

        sort($found);

        // 'sub' is an ordinary directory so it is still walked, at any depth
        $this->assertEquals(['insub', 'top', 'undersub'], $found);

        $this->removeTempDir($root);
    }

    public function testEmptyPruneWalksEverything(): void
    {
        $root = $this->makePruneTree();

        $found = $this->pruneInstance($root, ['prune' => []])->list();

        sort($found);

        $this->assertEquals([
            'ingit',
            'innode_modules',
            'insub',
            'top',
            'undergit',
            'undernode_modules',
            'undersub',
        ], $found);

        $this->removeTempDir($root);
    }

    public function testPruneIsConfigurable(): void
    {
        $root = $this->makePruneTree();

        // prune something the defaults do not, and stop pruning something they do
        $found = $this->pruneInstance($root, ['prune' => ['sub']])->list();

        sort($found);

        $this->assertEquals(['ingit', 'innode_modules', 'top', 'undergit', 'undernode_modules'], $found);

        $this->removeTempDir($root);
    }

    public function testPruneOnlyAppliesToRecursiveScans(): void
    {
        $root = $this->makePruneTree();

        // a non-recursive scan never descends anywhere, so pruning is moot - but
        // it must not accidentally start excluding the search path itself when
        // the search path is named like a pruned directory
        $instance = $this->pruneInstance($root . '/.git', ['recursive' => false, 'directories' => [$root . '/.git']]);

        $this->assertEquals(['ingit'], $instance->list());

        $this->removeTempDir($root);
    }

    /* pattern matching - the literal-suffix fast path vs the fnmatch fallback */

    public function testLeadingStarPatternMatchesBySuffix(): void
    {
        $root = $this->makeTempDir('orange-pattern-');

        file_put_contents($root . '/a.php', '<?php');
        file_put_contents($root . '/b.html', 'x');
        // a name that ends in the suffix without a dot boundary still matches,
        // exactly as fnmatch('*.php', ...) would not - guard the boundary
        file_put_contents($root . '/c.phpx', 'x');

        $found = $this->pruneInstance($root, ['match' => '*.php'])->list();

        $this->assertEquals(['a'], $found);

        $this->removeTempDir($root);
    }

    public function testBareStarPatternFallsBackToFnmatchAndMatchesEverything(): void
    {
        $root = $this->makeTempDir('orange-pattern-');

        file_put_contents($root . '/a.php', '<?php');
        file_put_contents($root . '/b.html', 'x');

        $found = $this->pruneInstance($root, ['match' => '*'])->list();

        sort($found);

        $this->assertEquals(['a', 'b'], $found);

        $this->removeTempDir($root);
    }

    public function testPatternWithMetacharactersFallsBackToFnmatch(): void
    {
        $root = $this->makeTempDir('orange-pattern-');

        file_put_contents($root . '/a.php', '<?php');
        file_put_contents($root . '/b.phg', 'x');
        file_put_contents($root . '/c.html', 'x');

        // a character class cannot be handled as a literal suffix
        $found = $this->pruneInstance($root, ['match' => '*.ph[pg]'])->list();

        sort($found);

        $this->assertEquals(['a', 'b'], $found);

        $this->removeTempDir($root);
    }

    public function testPatternWithoutLeadingStarFallsBackToFnmatch(): void
    {
        $root = $this->makeTempDir('orange-pattern-');

        file_put_contents($root . '/bar.php', '<?php');
        file_put_contents($root . '/foo.php', '<?php');

        $found = $this->pruneInstance($root, ['match' => 'bar.php'])->list();

        $this->assertEquals(['bar'], $found);

        $this->removeTempDir($root);
    }

    public function testDirectoryNamedLikeTheMatchPatternIsNotAResource(): void
    {
        $root = $this->makeTempDir('orange-pattern-');

        mkdir($root . '/looks-like-a-view.php');
        file_put_contents($root . '/looks-like-a-view.php/real.php', '<?php');

        $found = $this->pruneInstance($root)->list();

        // the directory is a container, so it is never yielded as a leaf
        $this->assertEquals(['real'], $found);

        $this->removeTempDir($root);
    }

    /* scan result caching */

    /**
     * Minimal in-memory CacheInterface that records what was asked of it.
     */
    protected function makeCache(): object
    {
        return new class implements \orange\framework\interfaces\CacheInterface {
            public array $storage = [];
            public array $gets = [];
            public array $sets = [];

            public function get(string $key): mixed
            {
                $this->gets[] = $key;

                return $this->storage[$key] ?? null;
            }

            public function set(string $key, mixed $value, ?int $ttl = null): bool
            {
                $this->sets[$key] = $ttl;
                $this->storage[$key] = $value;

                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->storage[$key]);

                return true;
            }

            public function flush(): bool
            {
                $this->storage = [];

                return true;
            }

            public function getMulti(array $keys): array
            {
                $set = [];

                foreach ($keys as $key) {
                    $set[$key] = $this->get($key);
                }

                return $set;
            }

            public function setMulti(array $data, ?int $ttl = null): array
            {
                $set = [];

                foreach ($data as $key => $value) {
                    $set[$key] = $this->set($key, $value, $ttl);
                }

                return $set;
            }

            public function deleteMulti(array $keys): array
            {
                $set = [];

                foreach ($keys as $key) {
                    $set[$key] = $this->delete($key);
                }

                return $set;
            }

            public function increment(string $key, int $offset = 1, ?int $ttl = null): int
            {
                return 0;
            }

            public function decrement(string $key, int $offset = 1, ?int $ttl = null): int
            {
                return 0;
            }
        };
    }

    public function testScanResultsArePersistedToTheCache(): void
    {
        $root = $this->makeTempDir('orange-cache-');
        file_put_contents($root . '/one.php', '<?php');

        $cache = $this->makeCache();

        $this->assertEquals(['one'], $this->pruneInstance($root, ['cache' => $cache])->list());

        // one entry, keyed per directory
        $this->assertCount(1, $cache->storage);
        $this->assertEquals([realpath($root . '/one.php')], reset($cache->storage));

        $this->removeTempDir($root);
    }

    public function testASecondInstanceReadsTheCacheInsteadOfWalkingTheTree(): void
    {
        $root = $this->makeTempDir('orange-cache-');
        file_put_contents($root . '/one.php', '<?php');

        // a real file the walk could never turn up, because it is not under the
        // search path at all
        $outside = $this->makeTempDir('orange-outside-');
        file_put_contents($outside . '/substituted.php', '<?php');

        $cache = $this->makeCache();

        $this->pruneInstance($root, ['cache' => $cache])->list();

        // swap the stored result. A second instance can only report this if it
        // took the cached list rather than walking the directory again
        $key = array_key_first($cache->storage);
        $cache->storage[$key] = [realpath($outside . '/substituted.php')];

        $this->assertEquals(['substituted'], $this->pruneInstance($root, ['cache' => $cache])->list());

        $this->removeTempDir($outside);
        $this->removeTempDir($root);
    }

    /**
     * A cached path is still handed to addResource(), which realpath()s it, so a
     * file deleted since the entry was written drops out on its own. That is the
     * cheap half of staleness - the walk is skipped but each cached path is
     * still confirmed - and it is why a stale entry degrades to a miss rather
     * than to a view that cannot be required. The other half, a file *added*
     * since the write, is the one flushCache() exists for.
     */
    public function testCachedPathsThatNoLongerExistAreDropped(): void
    {
        $root = $this->makeTempDir('orange-cache-');
        file_put_contents($root . '/one.php', '<?php');
        file_put_contents($root . '/two.php', '<?php');

        $cache = $this->makeCache();

        $found = $this->pruneInstance($root, ['cache' => $cache])->list();
        sort($found);
        $this->assertEquals(['one', 'two'], $found);

        unlink($root . '/two.php');

        $this->assertEquals(['one'], $this->pruneInstance($root, ['cache' => $cache])->list());

        $this->removeTempDir($root);
    }

    public function testCacheEntriesAreKeyedPerDirectoryPatternAndRecursion(): void
    {
        $root = $this->makeTempDir('orange-cache-');
        mkdir($root . '/sub');
        file_put_contents($root . '/one.php', '<?php');
        file_put_contents($root . '/sub/two.php', '<?php');

        $cache = $this->makeCache();

        // same directory, three different scan configurations
        $this->pruneInstance($root, ['cache' => $cache])->list();
        $this->pruneInstance($root, ['cache' => $cache, 'recursive' => false])->list();
        $this->pruneInstance($root, ['cache' => $cache, 'match' => '*.html'])->list();

        $this->assertCount(3, $cache->storage);

        // and the non-recursive one really did store the narrower result rather
        // than colliding with the recursive entry
        $this->assertEquals(['one'], $this->pruneInstance($root, ['cache' => $cache, 'recursive' => false])->list());

        $this->removeTempDir($root);
    }

    /**
     * Two instances differing only in what they prune are asking two different
     * questions about the same directory, so they must not share an entry. They
     * used to: prune was left out of the key, and whichever instance scanned
     * first silently answered for the other.
     */
    public function testCacheEntriesAreKeyedPerPruneList(): void
    {
        $root = $this->makePruneTree();

        $cache = $this->makeCache();

        $pruned = $this->pruneInstance($root, ['cache' => $cache])->list();
        sort($pruned);
        $this->assertEquals(['insub', 'top', 'undersub'], $pruned);

        $everything = $this->pruneInstance($root, ['cache' => $cache, 'prune' => []])->list();
        sort($everything);
        $this->assertEquals([
            'ingit',
            'innode_modules',
            'insub',
            'top',
            'undergit',
            'undernode_modules',
            'undersub',
        ], $everything);

        $this->assertCount(2, $cache->storage);

        $this->removeTempDir($root);
    }

    /**
     * The prune list is a set - listing the same names in a different order is
     * the same question and has to hit the same entry.
     */
    public function testPruneListOrderDoesNotChangeTheCacheKey(): void
    {
        $root = $this->makePruneTree();

        $cache = $this->makeCache();

        $this->pruneInstance($root, ['cache' => $cache, 'prune' => ['.git', 'sub']])->list();
        $this->pruneInstance($root, ['cache' => $cache, 'prune' => ['sub', '.git']])->list();

        $this->assertCount(1, $cache->storage);

        $this->removeTempDir($root);
    }

    /**
     * The opposite case, and deliberate: what a scan *found* is independent of
     * how an instance names it, so instances differing only in key style share
     * one entry - and both still answer correctly, because addMatches() derives
     * the keys per instance from the cached path list.
     */
    public function testInstancesDifferingOnlyInKeyStyleShareOneEntry(): void
    {
        $root = $this->makeTempDir('orange-cache-');
        file_put_contents($root . '/one.php', '<?php');

        $cache = $this->makeCache();

        $this->assertEquals(['one'], $this->pruneInstance($root, ['cache' => $cache])->list());
        $this->assertEquals(['one.php'], $this->pruneInstance($root, [
            'cache' => $cache,
            'resource key style' => 'basename',
        ])->list());

        $this->assertCount(1, $cache->storage);

        $this->removeTempDir($root);
    }

    public function testAnEmptyScanResultIsCachedRatherThanRescanned(): void
    {
        $root = $this->makeTempDir('orange-cache-');

        $cache = $this->makeCache();

        $this->assertEquals([], $this->pruneInstance($root, ['cache' => $cache])->list());
        $this->assertCount(1, $cache->storage);

        // a file appearing afterwards is exactly what a cached entry is allowed
        // to miss - that is what flushCache() is for
        file_put_contents($root . '/late.php', '<?php');

        $this->assertEquals([], $this->pruneInstance($root, ['cache' => $cache])->list());

        $this->removeTempDir($root);
    }

    public function testFlushCacheDropsEntriesForRegisteredDirectories(): void
    {
        $root = $this->makeTempDir('orange-cache-');

        $cache = $this->makeCache();

        $this->pruneInstance($root, ['cache' => $cache])->list();
        $this->assertCount(1, $cache->storage);

        file_put_contents($root . '/late.php', '<?php');

        $instance = $this->pruneInstance($root, ['cache' => $cache]);
        $this->assertInstanceOf(DirectorySearchInterface::class, $instance->flushCache());
        $this->assertCount(0, $cache->storage);

        // and a fresh instance now sees the file that appeared
        $this->assertEquals(['late'], $this->pruneInstance($root, ['cache' => $cache])->list());

        $this->removeTempDir($root);
    }

    public function testSetCacheAttachesAndDetaches(): void
    {
        $root = $this->makeTempDir('orange-cache-');
        file_put_contents($root . '/one.php', '<?php');

        $cache = $this->makeCache();

        $instance = new DirectorySearch([
            'quiet' => true,
            'recursive' => true,
            'match' => '*.php',
            'resource key style' => 'filename',
        ]);

        $this->assertInstanceOf(DirectorySearchInterface::class, $instance->setCache($cache, 1234));

        $instance->addDirectory($root);
        $instance->list();

        $this->assertCount(1, $cache->storage);
        // the ttl passed to setCache() is the one handed to the cache on write
        $this->assertEquals([1234], array_values($cache->sets));

        // detaching stops it being consulted at all
        $instance->setCache(null);
        $countBefore = count($cache->gets);

        $this->pruneInstance($root)->list();

        $this->assertCount($countBefore, $cache->gets);

        $this->removeTempDir($root);
    }

    public function testFlushCacheWithoutACacheIsANoop(): void
    {
        $root = $this->makeTempDir('orange-cache-');

        $instance = $this->pruneInstance($root);

        $this->assertInstanceOf(DirectorySearchInterface::class, $instance->flushCache());

        $this->removeTempDir($root);
    }

    public function testConstructorRejectsANonCache(): void
    {
        $this->expectException(\orange\framework\exceptions\InvalidValue::class);

        new DirectorySearch(['quiet' => true, 'cache' => 'not-a-cache']);
    }
}
