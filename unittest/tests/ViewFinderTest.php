<?php

declare(strict_types=1);

use orange\framework\ViewFinder;
use orange\framework\exceptions\view\ViewNotFound;
use orange\framework\interfaces\ViewFinderInterface;

final class ViewFinderTest extends unitTestHelper
{
    protected $instance = null;

    /**
     * A package ships 'blog/post/show' and 'errors/html/404' as fallbacks; the
     * welcome module has its own 'main/index' plus an override of the package's
     * 'blog/post/show'. Paths are never opened, so they do not have to exist.
     */
    protected array $views = [
        'application/welcome/main/index' => '/app/application/welcome/views/main/index.php',
        'application/welcome/blog/post/show' => '/app/application/welcome/views/blog/post/show.php',
    ];

    protected array $fallbacks = [
        'main/index' => '/app/application/welcome/views/main/index.php',
        'blog/post/show' => '/app/application/welcome/views/blog/post/show.php',
        'errors/html/404' => '/app/vendor/orange/framework/src/views/errors/html/404.php',
    ];

    protected array $aliases = [
        'dashboard' => 'main/index',
        'oops' => 'errors/html/404',
    ];

    protected function setUp(): void
    {
        $this->instance = ViewFinder::newInstance([
            'views' => $this->views,
            'view fallbacks' => $this->fallbacks,
            'view aliases' => $this->aliases,
        ]);
    }

    /* namespaced lookups */

    public function testNamespacedViewWins(): void
    {
        $this->assertEquals(
            $this->views['application/welcome/main/index'],
            $this->instance->find('main/index', 'application/welcome')
        );
    }

    /**
     * The override mechanism: a module asking for a view it happens to own gets
     * its own copy, not the shared one, purely because the namespaced key hits
     * first.
     */
    public function testModuleOverrideBeatsTheSharedView(): void
    {
        $shared = ViewFinder::newInstance([
            'views' => [],
            'view fallbacks' => ['blog/post/show' => '/app/vendor/acme/blog/src/views/blog/post/show.php'],
        ]);

        // no namespaced entry -> the package's copy
        $this->assertEquals('/app/vendor/acme/blog/src/views/blog/post/show.php', $shared->find('blog/post/show', 'application/welcome'));

        // with one -> the module's copy
        $this->assertEquals(
            $this->views['application/welcome/blog/post/show'],
            $this->instance->find('blog/post/show', 'application/welcome')
        );
    }

    /**
     * A module that does not own the view still resolves, by inheriting the
     * shared one. This is the common case for framework error views.
     */
    public function testFallsBackWhenTheNamespaceHasNoCopy(): void
    {
        $this->assertEquals(
            $this->fallbacks['errors/html/404'],
            $this->instance->find('errors/html/404', 'application/welcome')
        );
    }

    /**
     * Omitting the namespace is how a caller asks for the shared view
     * specifically, skipping any module override.
     */
    public function testWithoutANamespaceOnlyTheFallbackMapIsConsulted(): void
    {
        $instance = ViewFinder::newInstance([
            'views' => ['application/welcome/only/here' => '/app/only/here.php'],
            'view fallbacks' => [],
        ]);

        $this->assertFalse($instance->exists('only/here'));
        $this->assertTrue($instance->exists('only/here', 'application/welcome'));
    }

    /**
     * A namespace only ever prefixes - it must not make an unrelated namespaced
     * entry reachable.
     */
    public function testANamespaceDoesNotLeakOtherNamespacesEntries(): void
    {
        $instance = ViewFinder::newInstance([
            'views' => ['application/admin/main/index' => '/app/admin.php'],
            'view fallbacks' => [],
        ]);

        $this->assertFalse($instance->exists('main/index', 'application/welcome'));
        $this->assertTrue($instance->exists('main/index', 'application/admin'));
    }

    /* misses */

    public function testMissingViewThrows(): void
    {
        $this->expectException(ViewNotFound::class);

        $this->instance->find('no/such/view');
    }

    /**
     * "View not found" without the keys it tried sends people looking in the
     * wrong directory, so both attempts belong in the message.
     */
    public function testTheExceptionNamesBothAttemptedKeys(): void
    {
        try {
            $this->instance->find('no/such/view', 'application/welcome');
            $this->fail('expected ViewNotFound');
        } catch (ViewNotFound $e) {
            $this->assertStringContainsString('application/welcome/no/such/view', $e->getMessage());
            $this->assertStringContainsString('no/such/view', $e->getMessage());
        }
    }

    public function testExistsDoesNotThrow(): void
    {
        $this->assertFalse($this->instance->exists('no/such/view'));
        $this->assertFalse($this->instance->exists('no/such/view', 'application/welcome'));
        $this->assertTrue($this->instance->exists('main/index'));
    }

    /* name handling */

    public function testSurroundingSlashesAreIgnored(): void
    {
        $expected = $this->views['application/welcome/main/index'];

        $this->assertEquals($expected, $this->instance->find('/main/index', 'application/welcome'));
        $this->assertEquals($expected, $this->instance->find('main/index/', 'application/welcome'));
        $this->assertEquals($expected, $this->instance->find('/main/index/', '/application/welcome/'));
    }

    /**
     * Matching is case insensitive. Only the incoming name is folded here - the
     * generator writes the maps already lower cased, so a lookup costs one fold
     * rather than a pass over the whole map.
     */
    public function testMatchingIsCaseInsensitive(): void
    {
        $expected = $this->views['application/welcome/main/index'];

        $this->assertEquals($expected, $this->instance->find('Main/Index', 'application/welcome'));
        $this->assertEquals($expected, $this->instance->find('MAIN/INDEX', 'APPLICATION/WELCOME'));
        $this->assertEquals($expected, $this->instance->find('mAiN/iNdEx', 'Application/Welcome'));

        $this->assertTrue($this->instance->exists('ERRORS/HTML/404'));
    }

    /* aliases */

    /**
     * An alias is translated before either map is consulted, so it resolves
     * exactly as if the target name had been typed - including the module
     * override that name would get.
     */
    public function testAliasResolvesToItsTarget(): void
    {
        $this->assertEquals(
            $this->views['application/welcome/main/index'],
            $this->instance->find('dashboard', 'application/welcome')
        );

        // and without a namespace it lands on the fallback map, same as
        // asking for 'main/index' directly would
        $this->assertEquals($this->fallbacks['main/index'], $this->instance->find('dashboard'));
    }

    public function testAliasIsCaseInsensitiveToo(): void
    {
        $this->assertEquals($this->fallbacks['main/index'], $this->instance->find('DashBoard'));
    }

    /**
     * An alias points at a name, not a file, so it gets whatever that name
     * currently resolves to - here the shared error view, since welcome has no
     * copy of its own.
     */
    public function testAliasFollowsTheNormalFallbackOrder(): void
    {
        $this->assertEquals(
            $this->fallbacks['errors/html/404'],
            $this->instance->find('oops', 'application/welcome')
        );
    }

    /**
     * One hop only. A chain nobody can follow in a config file is worse than a
     * miss, and resolving it would need loop detection to be safe.
     */
    public function testAliasesAreNotFollowedTransitively(): void
    {
        $instance = ViewFinder::newInstance([
            'views' => [],
            'view fallbacks' => ['real/view' => '/app/real.php'],
            'view aliases' => ['first' => 'second', 'second' => 'real/view'],
        ]);

        $this->assertEquals('/app/real.php', $instance->find('second'));
        $this->assertFalse($instance->exists('first'));
    }

    /**
     * An alias whose key is also a real view name wins, because aliasing runs
     * before the maps are consulted. That is what makes an alias able to
     * redirect an existing name rather than only fill an empty one.
     */
    public function testAnAliasShadowsARealNameOfTheSameKey(): void
    {
        $instance = ViewFinder::newInstance([
            'views' => [],
            'view fallbacks' => ['a' => '/app/a.php', 'b' => '/app/b.php'],
            'view aliases' => ['a' => 'b'],
        ]);

        $this->assertEquals('/app/b.php', $instance->find('a'));
    }

    public function testTheExceptionNamesTheAliasTarget(): void
    {
        $instance = ViewFinder::newInstance([
            'views' => [],
            'view fallbacks' => [],
            'view aliases' => ['dashboard' => 'main/index'],
        ]);

        try {
            $instance->find('dashboard', 'application/welcome');
            $this->fail('expected ViewNotFound');
        } catch (ViewNotFound $e) {
            // the keys actually tried are the translated ones - reporting
            // 'dashboard' would send someone looking for a file by that name
            $this->assertStringContainsString('application/welcome/main/index', $e->getMessage());
        }
    }

    /**
     * A path is resolved, not opened - a stale generated map is the view
     * engine's problem to report when it goes to render, and conflating the two
     * failures hides which one happened.
     */
    public function testExistenceOfTheFileOnDiskIsNotChecked(): void
    {
        $instance = ViewFinder::newInstance([
            'views' => [],
            'view fallbacks' => ['gone' => '/definitely/not/here.php'],
        ]);

        $this->assertEquals('/definitely/not/here.php', $instance->find('gone'));
    }

    /* everything else */

    public function testAllReturnsBothMaps(): void
    {
        $all = $this->instance->all();

        foreach (array_keys($this->views) as $key) {
            $this->assertArrayHasKey($key, $all);
        }

        foreach (array_keys($this->fallbacks) as $key) {
            $this->assertArrayHasKey($key, $all);
        }

        $this->assertCount(count($this->views) + count($this->fallbacks), $all);
    }

    public function testEmptyConfigStillConstructsAndMissesCleanly(): void
    {
        $instance = ViewFinder::newInstance([]);

        $this->assertInstanceOf(ViewFinderInterface::class, $instance);
        $this->assertEquals([], $instance->all());
        $this->assertFalse($instance->exists('anything'));
    }

    public function testDebugInfoReportsBothMaps(): void
    {
        $debug = $this->instance->__debugInfo();

        $this->assertArrayHasKey('views', $debug);
        $this->assertArrayHasKey('view fallbacks', $debug);
    }
}
