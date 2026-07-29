<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\base\Singleton;
use orange\framework\traits\ConfigurationTrait;
use orange\framework\exceptions\view\ViewNotFound;
use orange\framework\interfaces\ViewFinderInterface;

/**
 * Overview of ViewFinder.php
 *
 * Resolves a view name to an absolute file path, and nothing else. It does not
 * open, read or render anything - handing the resulting path to a view engine
 * is the caller's job.
 *
 * ⸻
 *
 * 1. Why it is not part of the view engine
 *
 * The view engine used to do this itself, by scanning directories on every
 * request through a DirectorySearch. That coupled three unrelated things into
 * one class: what a view is named, where view files live, and how a template is
 * executed. Splitting the first two out means the engine can be handed a path
 * and be done, the naming rules can be tested without a filesystem, and a
 * pre-generated map can replace the scan entirely in production.
 *
 * ⸻
 *
 * 2. The two maps
 *
 * Both come from the application's generated view config:
 *
 *   'views'           'application/welcome/main/index' => '/abs/.../index.php'
 *   'view fallbacks'  'main/index'                     => '/abs/.../index.php'
 *
 * The namespaced map is unique by construction - the namespace is the PSR-4
 * root that owns the file - so nothing in it can collide. The fallback map is
 * where a vendor package's views live, keyed by everything after their views/
 * directory, and where two packages *can* collide: first one wins, which is why
 * a package should ship its views under views/<package>/ rather than at the top.
 *
 * A third section, 'view aliases', maps one name to another. It used to live on
 * the view engine as addAlias(), which meant the engine had opinions about what
 * a name meant - the same concern that moved out of it here.
 *
 * ⸻
 *
 * 3. How a name resolves
 *
 * A controller rendering 'main/index' supplies its own namespace, so:
 *
 *     find('main/index', 'application/welcome')
 *       0. alias, if 'main/index' is one
 *       1. 'application/welcome/main/index'  -> the module's own view
 *       2. 'main/index'                      -> whatever ships that key
 *
 * That ordering is the whole override mechanism: a module inherits a package's
 * view until someone drops a file of the same name into the module, at which
 * point step one starts hitting. Omitting the namespace skips step one, which
 * is how a caller asks for the shared view specifically.
 *
 * Aliasing happens first so an alias behaves exactly like typing the name it
 * stands for - it gets the same override rules rather than a second set.
 *
 * ⸻
 *
 * 4. Case
 *
 * Matching is case insensitive, but the folding is split: the generator writes
 * every key already lower cased, and only the incoming name is folded here. So
 * a lookup costs one mb_convert_case rather than a pass over the whole map, and
 * the generated file shows exactly the keys that will match. A map assembled by
 * hand rather than by the generator has to arrive lower cased to match.
 *
 * ⸻
 *
 * 5. What it deliberately does not do
 *
 * It does not check that the file exists. A name resolving to a path and that
 * path being readable are two different failures with two different causes -
 * a stale generated map versus a deleted file - and the view engine already
 * reports the second one when it goes to render.
 *
 * @package orange\framework
 *
 * Singleton::getInstance() cannot be redeclared with a narrower type in a
 * subclass without a fatal, so the concrete type is documented instead.
 * @method static self getInstance(array $config)
 */
class ViewFinder extends Singleton implements ViewFinderInterface
{
    use ConfigurationTrait;

    /** namespaced and unique: 'application/welcome/main/index' => absolute path */
    protected array $views = [];

    /** un-namespaced and first-wins: 'main/index' => absolute path */
    protected array $viewFallbacks = [];

    /** name => name, applied before either map is consulted */
    protected array $viewAliases = [];

    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::getInstance() instead
     *
     * @param array $config expects 'views', 'view fallbacks' and 'view aliases'
     *        sections, all with already lower cased keys
     */
    protected function __construct(array $config)
    {
        logMsg('DEBUG', __METHOD__);

        $this->config = $this->mergeConfigWith($config, false);

        // no folding of the maps here on purpose: the generator emits lower
        // cased keys, so folding them again would be a pass over the whole map
        // on every request to reach a fixed point they are already at
        $this->views = $this->config['views'];
        $this->viewFallbacks = $this->config['view fallbacks'];
        $this->viewAliases = $this->config['view aliases'];
    }

    /**
     * Absolute path for a view name.
     *
     * @param string $view view name, eg. 'main/index'
     * @param string $namespace owning namespace, eg. 'application/welcome'
     * @return string absolute path
     * @throws ViewNotFound when neither the namespaced nor the fallback key resolves
     */
    public function find(string $view, string $namespace = ''): string
    {
        $path = $this->locate($view, $namespace);

        if ($path === null) {
            // name both keys that were tried - "view not found" without them
            // sends people looking in the wrong directory
            throw new ViewNotFound('View "' . $view . '" not found. Tried ' . implode(' then ', $this->attemptedKeys($view, $namespace)) . '.');
        }

        return $path;
    }

    /**
     * Same lookup as find(), without throwing.
     */
    public function exists(string $view, string $namespace = ''): bool
    {
        return $this->locate($view, $namespace) !== null;
    }

    /**
     * Every known view name mapped to its absolute path.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->views + $this->viewFallbacks;
    }

    /**
     * output sent when var_dump is used on this class
     *
     * @return array{views: array, 'view fallbacks': array, 'view aliases': array}
     */
    public function __debugInfo(): array
    {
        return ['views' => $this->views, 'view fallbacks' => $this->viewFallbacks, 'view aliases' => $this->viewAliases];
    }

    /**
     * The shared lookup behind find() and exists(): alias first, then the
     * namespaced key, then the fallback key, null when nothing resolves.
     */
    protected function locate(string $view, string $namespace): ?string
    {
        $view = $this->resolveAlias($this->normalize($view));
        $namespace = $this->normalize($namespace);

        if ($namespace !== '') {
            $namespaced = $namespace . '/' . $view;

            if (isset($this->views[$namespaced])) {
                logMsg('DEBUG', __METHOD__ . ' "' . $namespaced . '" matched a namespaced view');

                return $this->views[$namespaced];
            }
        }

        if (isset($this->viewFallbacks[$view])) {
            // worth a line of its own: inheriting a shared view rather than using
            // the module's own is exactly the case people need to see when they
            // are asking why a render produced a file they did not write
            logMsg('DEBUG', __METHOD__ . ' "' . $view . '" matched a fallback view');

            return $this->viewFallbacks[$view];
        }

        logMsg('DEBUG', __METHOD__ . ' "' . $view . '" did not match, tried ' . implode(' then ', $this->attemptedKeys($view, $namespace)));

        return null;
    }

    /**
     * Translate an alias to the name it stands for.
     *
     * Applied before either map is consulted, so an alias behaves exactly like
     * typing the target name - it inherits the same namespaced-then-fallback
     * resolution rather than getting rules of its own. One hop only: an alias
     * pointing at another alias is a chain nobody can follow in a config file,
     * and resolving it would need loop detection to be safe.
     */
    protected function resolveAlias(string $view): string
    {
        $alias = $this->viewAliases[$view] ?? $view;

        if ($alias !== $view) {
            logMsg('DEBUG', __METHOD__ . ' "' . $view . '" is an alias for "' . $alias . '"');
        }

        return $alias;
    }

    /**
     * Fold a name for matching.
     *
     * Matching is case insensitive, but only this side of it is folded: the
     * generator writes the maps with lower cased keys already, so the work per
     * lookup is one name rather than one map.
     */
    protected function normalize(string $view): string
    {
        return mb_convert_case(trim($view, '/'), MB_CASE_LOWER, 'UTF-8');
    }

    /**
     * The keys locate() would try, in order - used for logging and the exception.
     *
     * @return list<string>
     */
    protected function attemptedKeys(string $view, string $namespace): array
    {
        $view = $this->resolveAlias($this->normalize($view));
        $namespace = $this->normalize($namespace);
        $keys = [];

        if ($namespace !== '') {
            $keys[] = '"' . $namespace . '/' . $view . '"';
        }

        $keys[] = '"' . $view . '"';

        return $keys;
    }
}
