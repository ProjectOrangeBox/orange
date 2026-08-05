<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * A priority-ordered set of directories, and the pool of resources found across
 * them.
 *
 * This is the "same name in several places" problem, factored out: config files,
 * views and modules all need a name to resolve against an ordered list of roots
 * where an earlier root wins. Directories carry the order - $pend says whether a
 * new one is PREPEND (higher priority) or APPEND - and resources are what the
 * scan turned up, keyed by name.
 *
 * A key can legitimately match in more than one directory, so the read side is
 * three methods rather than one, and the choice is the caller's: find() returns
 * every match in priority order, findFirst() the highest-priority one - the
 * usual "this overrides that" answer - and findLast() the lowest, which is what
 * an inheritance chain that builds up from a base wants.
 *
 * Resources may also be registered directly with addResource(), which is how a
 * cached or generated map is loaded without touching the filesystem. Those
 * entries are indistinguishable from scanned ones afterwards, which is why the
 * directory-removal methods take $removeFoundResources - by default they leave
 * behind what a removed directory contributed.
 *
 * Two mechanisms exist for the cost of scanning. setCache() persists per-
 * directory results across requests and must be attached before the first read.
 * lock() freezes the directory and resource sets so any later mutation throws
 * rather than silently invalidating a configuration that was loaded once from a
 * cache and is assumed final.
 *
 * FIRST/PREPEND and LAST/APPEND are two spellings of the same two values, used
 * interchangeably for the $pend argument - FIRST and PREPEND are both 1. They
 * describe where a directory lands, and have nothing to do with findFirst() and
 * findLast(), which take no constant.
 */
interface DirectorySearchInterface
{
    public const FIRST = 1;
    public const LAST = 2;
    public const PREPEND = 1;
    public const APPEND = 2;

    /**
     * add one or more directories
     */
    public function addDirectory(string $directory, ?int $pend = null): self;
    /** @param array<array-key, string> $directories */
    public function addDirectories(array $directories, ?int $pend = null): self;

    /**
     * remove one or more attached directories
     */
    public function removeDirectory(string $directory, bool $removeFoundResources = false): self;
    /** @param array<array-key, string> $directories */
    public function removeDirectories(array $directories, bool $removeFoundResources = false): self;

    /**
     * list all directories
     *
     * @return list<string> absolute directory paths, highest priority first
     */
    public function listDirectories(): array;

    /**
     * test if a directory already exists
     */
    public function directoryExists(string $directory): bool;

    /**
     * replace ALL directories or resources
     *
     * This can be used if loading from a cache for example
     *
     * @param array<array-key, string> $directories
     */
    public function replaceDirectories(array $directories, bool $removeFoundResources = false): self;


    /**
     * manually add 1 or more resources to the resource pool
     *
     * These can be removed when you call
     * removeDirectory()
     * removeDirectories()
     * replaceDirectories()
     *
     * use the second argument $removeFoundResources if this is a problem
     */
    public function addResource(string $resource, string $absolutePath): self;
    /** @param array<array-key, string> $resources */
    public function addResources(array $resources): self;

    public function removeResource(string $resource): self;
    /** @param array<array-key, string> $resources */
    public function removeResources(array $resources): self;

    /**
     * get a list of all the resources
     *
     * @return list<string> every known resource key
     */
    public function list(): array;

    /**
     * Test if a resource exists (any where)
     */
    public function exists(string $resource): bool;

    /**
     * NOTE this does not trigger a rescan of the current directories
     *
     * @param array<array-key, string> $resources
     */
    public function replaceResources(array $resources): self;

    // flush the found...
    public function flushDirectories(bool $flushResources = true): self;
    public function flushResources(): self;

    /**
     * attach a cache to persist per-directory scan results across requests,
     * or null to detach one
     *
     * attach it before the first read - directories already scanned in this
     * process are not re-scanned, so a late attachment caches nothing until
     * the next request
     *
     * flushCache() drops the entries for the currently registered directories,
     * which is what a deploy calls - stored entries do not otherwise notice a
     * file being added or removed
     */
    public function setCache(?CacheInterface $cache, ?int $ttl = null): self;
    public function flushCache(): self;

    /**
     * find all or the first or last matching resource
     *
     * @return list<string> absolute paths of every file matching this key
     */
    public function find(string $resource): array;
    public function findFirst(string $resource): string;
    public function findLast(string $resource): string;
    /** @return array<string, list<string>> resource key => its matching paths */
    public function findAll(): array;

    /**
     * this can be used to lock and unlock the class from
     * adding, removing, or replacing directories and resources
     * this might be helpful if you load the class once from a cache
     * if any of those are called while locked an exception will be thrown
     */
    public function lock(): self;
    public function unlock(): self;
    public function isLocked(): bool;
}
