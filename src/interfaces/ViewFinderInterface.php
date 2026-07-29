<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

use orange\framework\exceptions\view\ViewNotFound;

/**
 * Turns a view name into an absolute file path.
 *
 * This is the half of view handling that used to live inside the view engine.
 * A view engine renders a file; deciding which file that is - namespaces,
 * fallbacks, overrides - is a separate concern with separate rules, and this is
 * where those rules live. Nothing here reads or renders a file.
 *
 * Three sections back it, all produced by the application's view detector:
 *
 *   'views'          namespaced and unique - 'application/welcome/main/index'
 *   'view fallbacks' un-namespaced         - 'main/index', 'errors/html/404'
 *   'view aliases'   name => name          - applied before either map
 *
 * A package ships its views under the fallback keys; an application module
 * overrides one by holding the same key. Which module asked is supplied as the
 * $namespace argument, so the lookup is: alias first, your namespaced copy
 * second, the shared one last.
 *
 * Matching is case insensitive. Keys arrive already lower cased from the
 * generator; only the name being looked up is folded here.
 */
interface ViewFinderInterface
{
    /**
     * Absolute path for a view name.
     *
     * The name is aliased first, then - with a $namespace - '<namespace>/<view>'
     * is tried against the namespaced map before '<view>' is tried against the
     * fallback map. Without one, only the fallback map is consulted, which is
     * what a caller that does not want a module's local override wants.
     *
     * @throws ViewNotFound when no key resolves
     */
    public function find(string $view, string $namespace = ''): string;

    /**
     * Same lookup as find(), without throwing.
     */
    public function exists(string $view, string $namespace = ''): bool;

    /**
     * Every known view name mapped to its absolute path, namespaced entries
     * first. Intended for tooling and diagnostics rather than rendering.
     *
     * @return array<string, string>
     */
    public function all(): array;
}
