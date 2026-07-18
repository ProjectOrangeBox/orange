<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

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
    public function addDirectories(array $directories, ?int $pend = null): self;

    /**
     * remove one or more attached directories
     */
    public function removeDirectory(string $directory, bool $removeFoundResources = false): self;
    public function removeDirectories(array $directories, bool $removeFoundResources = false): self;

    /**
     * list all directories
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
    public function addResources(array $resources): self;

    public function removeResource(string $resource): self;
    public function removeResources(array $resources): self;

    /**
     * get a list of all the resources
     */
    public function list(): array;

    /**
     * Test if a resource exists (any where)
     */
    public function exists(string $resource): bool;

    /**
     * NOTE this does not trigger a rescan of the current directories
     */
    public function replaceResources(array $resources): self;

    // flush the found...
    public function flushDirectories(bool $flushResources = true): self;
    public function flushResources(): self;

    /**
     * find all or the first or last matching resource
     */
    public function find(string $resource): array;
    public function findFirst(string $resource): string;
    public function findLast(string $resource): string;
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
