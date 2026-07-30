<?php

declare(strict_types=1);

namespace orange\framework\helpers;

use Closure;
use orange\framework\exceptions\NotFound;
use orange\framework\exceptions\ClassLocked;
use orange\framework\exceptions\InvalidValue;
use orange\framework\interfaces\CacheInterface;
use orange\framework\traits\ConfigurationTrait;
use orange\framework\exceptions\ResourceNotFound;
use orange\framework\interfaces\DirectorySearchInterface;
use orange\framework\exceptions\filesystem\DirectoryNotFound;

class DirectorySearch implements DirectorySearchInterface
{
    use ConfigurationTrait;

    // interface constants: FIRST, LAST, PREPEND, APPEND

    // wildcard match for files (glob syntax)
    protected string $match = '';

    // directory path => has it been scanned yet? (see scanDirectories())
    /** @var array<string, bool> */
    protected array $directories = [];

    // array of "found" files
    /** @var array<string, array<string, null>> resource key => set of paths, ordered */
    protected array $resources = [];

    // set of resource keys matching more than one file - the only ones whose order
    // can be wrong, so a rescan sorts these instead of walking every resource.
    // Stale entries are harmless: the count check in the sort skips them.
    /** @var array<string, bool> */
    protected array $multiMatch = [];

    // throw exception if resource not found?
    protected bool $quiet = false;

    // search recursively in the directories?
    protected bool $recursive = false;

    // track if we need to run scanDirectories again
    protected bool $rescan = true;

    // ignore adding directories / resources
    protected bool $locked = false;

    // closure to extract the resource name from the path
    protected Closure $keyClosure;

    // lock the class (from add / remove) after the first full scan is done
    protected bool $lockAfterScan = false;

    // normalize keys?
    protected bool $normalizeKeys = true;

    // hash keys
    protected bool $hashKeys = true;

    // append or prepend by default?
    protected int $pend = self::FIRST;

    // callback method
    /** @var array{}|array{0: object|string, 1: string} */
    protected array $callback = [];

    // directory names never descended into during a recursive scan.
    // These cannot hold resources but can easily dwarf the tree that does -
    // a checked-out .git is routinely thousands of entries deep in a search
    // path that holds a dozen views
    /** @var array<array-key, string> */
    protected array $prune = [];

    // optional persistence for per-directory scan results (see scanForMatches())
    protected ?CacheInterface $cache = null;

    // ttl handed to the cache on write, null to use the cache's own default
    protected ?int $cacheTtl = null;

    /** @var array<string, mixed> */
    protected array $defaults = [
        'match' => '*.php', // glob format
        'quiet' => false, // throw exceptions when resource not found?
        'normalize keys' => true,
        'hash keys' => false, // if your keys are large is it helpful to hash them instead
        'recursive' => false, // recursive search directories
        'locked' => false, // does it start locked?
        'lock after scan' => false, // lock after first scan (read)
        'pend' => DirectorySearchInterface::PREPEND, // append or prepend new directories to search list?
        'callback' => [], // class::method
        'resource key style' => 'view', // can also be a custom closure
        'directories' => [], // startup defaults
        'resources' => [],
        // directory names skipped (subtree and all) by a recursive scan.
        // 'vendor' is deliberately absent - it is a legitimate directory name
        // that a resource tree can nest, unlike the tool directories below
        'prune' => ['.git', '.svn', '.hg', '.idea', '.vscode', 'node_modules'],
        'cache' => null, // a CacheInterface to persist scan results across requests
        'cache ttl' => null, // ttl for those entries, null to use the cache's default
    ];

    /**
     * Not a standalone class and not a singleton
     *
     * Constructor for DirectorySearch.
     *
     * Initializes the DirectorySearch instance with the provided configuration.
     * Merges the config with defaults, assigns properties, sets up resource key style,
     * and adds any default directories and resources.
     *
     * @param array<string, mixed> $config Configuration array for the DirectorySearch instance.
     * @throws InvalidValue If 'cache' is set to something that is not a CacheInterface.
     */
    public function __construct(array $config)
    {
        $config = array_replace($this->defaults, $config);

        // assignFromConfig() would surface a bad 'cache' as a raw TypeError on a
        // property assignment, which says nothing about which config key was wrong
        if ($config['cache'] !== null && !$config['cache'] instanceof CacheInterface) {
            throw new InvalidValue('"cache" must be a ' . CacheInterface::class . ', ' . get_debug_type($config['cache']) . ' given.');
        }

        // assign class properties based on config values where applicable
        $this->assignFromConfig($config);

        // indicate we need a rescan on the next read call
        $this->rescan();

        // setup the resource key style
        $this->setupResourceKeyStyle($config);

        // add any defaults?
        $this->flushDirectories(true)->addDirectories($config['directories'])->addResources($config['resources']);
    }


    /**
     * add new directory
     * default to prepend into the array (add to the front of the array)
     *
     * @param string $directory
     * @param int|null $pend
     * @return DirectorySearch
     * @throws ClassLocked
     * @throws NotFound
     * @throws DirectoryNotFound
     */
    public function addDirectory(string $directory, ?int $pend = null): self
    {
        // should we throw an exception?
        $this->ifLockedThrowException();

        $pend ??= $this->pend;

        if ($found = realpath(rtrim($directory, DIRECTORY_SEPARATOR))) {
            // re-adding a directory only moves it in the priority list - its
            // resources are still in $this->resources, so carry the scanned flag
            // over rather than making the next read walk it again
            $scanned = $this->directories[$found] ?? false;

            if ($pend == self::PREPEND) {
                // $found comes from the left operand, so it lands first
                $this->directories = [$found => $scanned] + $this->directories;
            } else {
                $this->directories[$found] = $scanned;
            }

            // force a rescan on next read
            $this->rescan();
            $this->callback('addDirectory');
        } elseif (!$this->quiet) {
            throw new DirectoryNotFound($directory);
        }

        return $this;
    }

    /**
     * add new directories
     * use the 3rd argument 'asBlock' to keep the directories array in order when adding
     *
     * @param array<array-key, string> $directories
     * @param int|null $pend
     * @param bool $asBlock
     * @return self
     * @throws ClassLocked
     * @throws NotFound
     * @throws DirectoryNotFound
     */
    public function addDirectories(array $directories, ?int $pend = null, bool $asBlock = true): self
    {
        $pend ??= $this->pend;

        // pre pend as a block in this exact order
        if ($pend == self::PREPEND && $asBlock) {
            $directories = array_reverse($directories);
        }

        foreach ($directories as $directory) {
            $this->addDirectory($directory, $pend);
        }

        return $this;
    }

    /**
     * remove if it matches the directory
     *
     * @param string $directory
     * @param bool $removeFoundResources
     * @return self
     * @throws ClassLocked
     */
    public function removeDirectory(string $directory, bool $removeFoundResources = true): self
    {
        $this->ifLockedThrowException();

        $directory = realpath(rtrim($directory, DIRECTORY_SEPARATOR));

        unset($this->directories[$directory]);

        if ($directory && $removeFoundResources) {
            $dirLength = strlen($directory);

            // $this->resources is keyed by resource name, and each entry is itself
            // a map of path => null (a resource name can match multiple files), so
            // matching against the directory requires walking that inner map too
            foreach ($this->resources as $resource => $paths) {
                foreach ($paths as $path => $null) {
                    if (substr((string) $path, 0, $dirLength) == $directory) {
                        unset($this->resources[$resource][$path]);
                    }
                }

                if (empty($this->resources[$resource])) {
                    unset($this->resources[$resource]);
                }
            }
        }

        $this->callback('removeDirectory');

        return $this;
    }

    /**
     * remove multiple directories
     *
     * @param array<array-key, string> $directories
     * @param bool $removeFoundResources
     * @return self
     * @throws ClassLocked
     */
    public function removeDirectories(array $directories, bool $removeFoundResources = true): self
    {
        foreach ($directories as $directory) {
            $this->removeDirectory($directory, $removeFoundResources);
        }

        return $this;
    }

    /**
     * list all directories
     *
     * @return list<string> absolute directory paths, highest priority first
     */
    public function listDirectories(): array
    {
        return array_keys($this->directories);
    }

    /**
     * replace all directories
     *
     * @param array<array-key, string> $directories
     * @param bool $removeFoundResources
     * @return self
     * @throws ClassLocked
     */
    public function replaceDirectories(array $directories, bool $removeFoundResources = true): self
    {
        $this->ifLockedThrowException();

        $this->flushDirectories();

        // replace them verbatim
        $this->addDirectories($directories);

        if ($removeFoundResources) {
            $this->resources = [];
        }

        $this->rescan();
        $this->callback('replaceDirectories');

        return $this;
    }

    /**
     * check if directory exists in the list
     *
     * @param string $directory
     * @return bool
     */
    public function directoryExists(string $directory): bool
    {
        // realpath() returns false for a path that does not exist, and
        // array_key_exists() takes int|string - so asking about a missing
        // directory raised a TypeError instead of answering false
        $resolved = realpath(rtrim($directory, DIRECTORY_SEPARATOR));

        return $resolved !== false && array_key_exists($resolved, $this->directories);
    }

    /**
     * flush all directories
     *
     * @param bool $flushResources
     * @return self
     */
    public function flushDirectories(bool $flushResources = true): self
    {
        $this->directories = [];

        if ($flushResources) {
            $this->flushResources();
        }

        $this->rescan();

        $this->callback('flushDirectories');

        return $this;
    }

    /* resources */

    /**
     * add a single resource
     *
     * @param string $resource
     * @param string $path
     * @return self
     * @throws ClassLocked
     */
    public function addResource(string $resource, string $path): self
    {
        // should we throw an exception?
        $this->ifLockedThrowException();

        if ($path = realpath($path)) {
            $key = $this->normalizeKey($resource);

            // there may actually be multiple matching resources for 1 resource key
            $this->resources[$key][$path] = null;

            // note the ones that need ordering by directory priority later
            if (count($this->resources[$key]) > 1) {
                $this->multiMatch[$key] = true;
            }
        }

        $this->callback('addResource');

        return $this;
    }

    /**
     * add multiple resources
     *
     * @param array<array-key, string> $resources
     * @return self
     */
    public function addResources(array $resources): self
    {
        foreach ($resources as $resource => $path) {
            $this->addResource($resource, $path);
        }

        return $this;
    }

    /**
     * replace all resources
     *
     * @param array<array-key, string> $resources
     * @return self
     */
    public function replaceResources(array $resources): self
    {
        // should we throw an exception?
        $this->ifLockedThrowException();

        return $this->flushResources()->addResources($resources);
    }

    /**
     * flush all resources
     *
     * @return self
     */
    public function flushResources(): self
    {
        $this->resources = [];
        $this->multiMatch = [];

        // every directory's findings just went away, so they all need re-walking
        $this->directories = array_fill_keys(array_keys($this->directories), false);

        $this->rescan();

        $this->callback('flushResources');

        return $this;
    }

    /**
     * remove a single resource
     *
     * @param string $resource
     * @return self
     * @throws ClassLocked
     */
    public function removeResource(string $resource): self
    {
        // should we throw an exception?
        $this->ifLockedThrowException();

        unset($this->resources[$this->normalizeKey($resource)]);

        return $this;
    }

    /**
     * remove multiple resources
     *
     * @param array<array-key, string> $resources
     * @return self
     */
    public function removeResources(array $resources): self
    {
        foreach ($resources as $key) {
            $this->removeResource($key);
        }

        return $this;
    }

    /**
     * find all matching resources for a given key
     *
     * @param string $resource
     * @return list<string> absolute paths of every file matching this key
     * @throws ResourceNotFound
     */
    public function find(string $resource): array
    {
        // search for all resources and put in $this->resources
        $this->scanDirectories();

        // normalize once - going through exists() here repeated both the scan
        // check and the normalize, and this is the path behind every view render
        $key = $this->normalizeKey($resource);

        // we are looking for a specific resource
        if (isset($this->resources[$key])) {
            return array_keys($this->resources[$key]);
        }

        if (!$this->quiet) {
            throw new ResourceNotFound($resource);
        }

        return [];
    }

    /**
     * find all resources
     *
     * @return array<string, list<string>> resource key => its matching paths
     */
    public function findAll(): array
    {
        $found = [];

        // search for all resources and put in $this->resources
        $this->scanDirectories();

        foreach ($this->resources as $resourceName => $resources) {
            $found[$resourceName] = array_keys($resources);
        }

        return $found;
    }

    /**
     * get a list of all resources
     *
     * @return list<string> every known resource key
     * @throws NotFound
     */
    public function list(): array
    {
        $this->scanDirectories();

        return array_keys($this->resources);
    }

    /**
     * Find the first matching resource
     *
     * @param string $resource
     * @return string
     * @throws NotFound
     * @throws ResourceNotFound
     */
    public function findFirst(string $resource): string
    {
        $found = $this->find($resource);

        // guard the empty case explicitly - array_key_first([]) is null, and
        // $found[null] is deprecated in 8.5 (a hard error in 9), so the ?? ''
        // that used to absorb it was emitting a notice on every miss
        return $found === [] ? '' : $found[array_key_first($found)];
    }

    /**
     * Find the last matching resource
     *
     * @param string $resource
     * @return string
     * @throws NotFound
     * @throws ResourceNotFound
     */
    public function findLast(string $resource): string
    {
        $found = $this->find($resource);

        // same empty guard as findFirst()
        return $found === [] ? '' : $found[array_key_last($found)];
    }

    /**
     * Does this resource exist in any directory?
     *
     * @param string $resource
     * @return bool
     */
    public function exists(string $resource): bool
    {
        $this->scanDirectories();

        return array_key_exists($this->normalizeKey($resource), $this->resources);
    }

    /**
     * Attach (or detach, with null) a cache to persist scan results across
     * requests.
     *
     * Attach it before the first read. Directories already scanned in this
     * process stay scanned, so a cache attached afterwards has nothing to hand
     * back and only starts paying off on the next request.
     *
     * The cache is not part of the constructor's config in every case because
     * the natural place to get one is the container, and a config file is loaded
     * too early to reach into it. Config or setter, either works.
     *
     * @param CacheInterface|null $cache
     * @param int|null $ttl ttl for written entries, null for the cache's default
     * @return self
     */
    public function setCache(?CacheInterface $cache, ?int $ttl = null): self
    {
        $this->cache = $cache;
        $this->cacheTtl = $ttl;

        return $this;
    }

    /**
     * Drop the cached scan results for the currently registered directories.
     *
     * Nothing about a stored entry expires when a file is added or removed - the
     * paths were true when they were written and there is no cheap way to notice
     * otherwise - so this is what a deploy calls. It only knows about the
     * directories registered right now; a directory that has since been removed
     * from the list keeps its entry until the cache's own ttl retires it.
     *
     * @return self
     */
    public function flushCache(): self
    {
        if ($this->cache !== null) {
            $keys = [];

            foreach (array_keys($this->directories) as $directory) {
                $keys[] = $this->cacheKey((string)$directory);
            }

            if ($keys !== []) {
                $this->cache->deleteMulti($keys);
            }
        }

        return $this;
    }

    /**
     * lock the class from further modification
     *
     * @return DirectorySearch
     */
    public function lock(): self
    {
        $this->locked = true;

        return $this;
    }

    /**
     * unlock the class
     * This does not check it simple unlocks it
     *
     * @return DirectorySearch
     */
    public function unlock(): self
    {
        $this->locked = false;

        return $this;
    }

    /**
     * get if the class is locked
     *
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * output sent when var_dump is used on this class
     *
     * @return array{resources: array<string, array<string, null>>, directories: array<string, bool>}
     */
    public function __debugInfo()
    {
        return ['resources' => $this->resources, 'directories' => $this->directories];
    }

    /**
     * Scan all of the directorys for matching resources
     *
     * @return void
     * @throws NotFound
     */
    protected function scanDirectories(): void
    {
        if ($this->rescan) {
            // Only walk directories not already walked. Adding a directory used to
            // invalidate the whole scan and re-glob every directory on the next
            // read, which is the common case - BaseController adds the controller's
            // own views directory on every request - so a request paid a full
            // re-walk of the entire view tree to pick up one new directory.
            //
            // This produces the same $this->resources as the old full re-walk:
            // addResource() assigns into an existing key without moving it, so
            // re-adding an already-known path was always a no-op, and only the
            // paths under a not-yet-scanned directory were ever appended.
            foreach ($this->directories as $directory => $scanned) {
                if ($scanned) {
                    continue;
                }

                if ($searchPath = realpath(rtrim((string) $directory, DIRECTORY_SEPARATOR))) {
                    $this->addMatches($searchPath, $this->cachedMatches($searchPath));
                }

                // mark scanned even when realpath() failed - a directory that does
                // not resolve will not resolve on the next read either
                $this->directories[$directory] = true;
            }

            // a scan appends to whatever was already found, and addDirectory() can
            // reorder the priority list without finding anything new, so re-derive
            // the per-resource ordering here rather than at every read
            $this->sortResourcesByDirectoryPriority();

            if ($this->lockAfterScan) {
                $this->lock();
            }

            $this->rescan = false;
        }
    }

    /**
     * Return the matching files under one search path, going through the cache
     * when one is attached.
     *
     * Caching happens per directory rather than per instance on purpose. The
     * directory set is not fixed for the life of the process - BaseController
     * registers the running controller's own views directory on every request -
     * so a single cached blob keyed on the whole set would miss constantly.
     * Keyed per directory, every request reuses the entries for the directories
     * it shares with previous requests and only pays for genuinely new ones,
     * and the cross-directory work (the union, and the priority sort below)
     * stays in process where it is cheap.
     *
     * What a cached entry skips is the tree walk, not every filesystem call:
     * addMatches() still puts each path through addResource(), which realpath()s
     * it. So a file deleted since the entry was written drops out on its own,
     * and a stale entry degrades to a miss rather than to a resource that cannot
     * be read. A file *added* since the write is the case nothing here can
     * notice - that is what flushCache() and the ttl are for.
     *
     * @param string $searchPath an already realpath()ed directory
     * @return list<string> absolute paths of the matching files
     */
    protected function cachedMatches(string $searchPath): array
    {
        if ($this->cache === null) {
            return $this->scanForMatches($searchPath);
        }

        $key = $this->cacheKey($searchPath);

        // an is_array() check, not isset()/!== null: an empty result is a real
        // answer worth caching, and every cache returns null for a miss
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            /** @var list<string> $cached a previous scanForMatches() result */
            return $cached;
        }

        $matches = $this->scanForMatches($searchPath);

        $this->cache->set($key, $matches, $this->cacheTtl);

        return $matches;
    }

    /**
     * Walk one search path for matching files, with no caching in between.
     *
     * @param string $searchPath an already realpath()ed directory
     * @return list<string> absolute paths of the matching files
     */
    protected function scanForMatches(string $searchPath): array
    {
        if ($this->recursive) {
            return $this->recursiveGlob($searchPath, $this->match);
        }

        // glob() reports failure as false, which is not something the caller
        // should have to keep re-checking
        return glob($searchPath . DIRECTORY_SEPARATOR . $this->match) ?: [];
    }

    /**
     * Cache key for one search path's scan results.
     *
     * Every input scanForMatches() reads has to be in here, or two instances
     * configured differently silently share one entry and the second one gets
     * the first one's answer. That is the whole list: the path, the pattern, the
     * recursion flag, and the prune list.
     *
     * What is deliberately *not* in here is anything applied after the scan -
     * 'resource key style', 'normalize keys', 'hash keys'. Those shape the keys
     * of $this->resources, not which files were found, and addMatches() re-derives
     * them per instance from the cached path list. Two instances that disagree
     * only about key style share an entry and both stay correct, which is the
     * point: one scan feeds however many differently-keyed views of it.
     *
     * Instances of DirectorySearch are otherwise free to differ - 'quiet',
     * 'locked', 'pend', 'callback' and the directory list itself cannot change
     * what a scan of one directory turns up. The directory list in particular is
     * why this is keyed per directory in the first place.
     *
     * @param string $searchPath
     * @return string
     */
    protected function cacheKey(string $searchPath): string
    {
        // prune is a set, so a differently ordered but equivalent list must not
        // produce a different key
        $prune = $this->prune;
        sort($prune);

        return 'directorysearch.' . sha1(implode('|', [
            $searchPath,
            $this->match,
            $this->recursive ? 'r' : 'n',
            implode(',', $prune),
        ]));
    }

    /**
     * Order each resource's matching paths by the current priority of the
     * directory they were found under, so find() returns them highest priority
     * first and findFirst() returns the winner.
     *
     * Without this the paths stay in the order they were discovered: resources
     * are never cleared between scans and re-assigning an existing key does not
     * move it, so a directory added with PREPEND/FIRST after an earlier scan
     * never actually took precedence. That is precisely the case BaseController
     * relies on when it registers a controller's own views directory as FIRST to
     * override a same-named shared view.
     *
     * @return void
     */
    protected function sortResourcesByDirectoryPriority(): void
    {
        // nothing matched in two places means nothing can be out of order
        if ($this->multiMatch === []) {
            return;
        }

        // $this->directories is already in priority order, so its index is the
        // rank. Prepare the comparison form once here rather than inside
        // directoryRank(): the separator concatenation and the strlen() are the
        // same for every path ranked below, and this used to redo both per
        // directory per path
        $directories = [];

        foreach (array_keys($this->directories) as $index => $directory) {
            $prefix = (string)$directory . DIRECTORY_SEPARATOR;

            $directories[] = [$prefix, strlen($prefix), $index];
        }

        foreach (array_keys($this->multiMatch) as $resource) {
            $paths = $this->resources[$resource] ?? [];

            // the set is only ever added to, so an entry can be stale by the time
            // it is read - a removeResource()/removeDirectory() in between
            if (count($paths) < 2) {
                continue;
            }

            $ranked = [];

            foreach (array_keys($paths) as $path) {
                $ranked[$path] = $this->directoryRank((string)$path, $directories);
            }

            // sorts are stable as of PHP 8, so paths sharing a directory keep the
            // order the scan found them in
            asort($ranked);

            $sorted = [];

            foreach (array_keys($ranked) as $path) {
                $sorted[$path] = $paths[$path];
            }

            $this->resources[$resource] = $sorted;
        }
    }

    /**
     * Rank a found path by which registered directory it lives under.
     *
     * Longest matching prefix wins, because registered directories can nest and
     * the innermost one is the one that actually owns the file. A path under no
     * registered directory - anything handed to addResource() directly - ranks
     * last, keeping it a fallback behind whatever a scan turned up.
     *
     * @param string $path
     * @param array<array-key, array{string, int, int}> $directories prepared [prefix, prefix length, rank] triples,
     *        already in priority order - see sortResourcesByDirectoryPriority()
     * @return int
     */
    protected function directoryRank(string $path, array $directories): int
    {
        $rank = PHP_INT_MAX;
        $longestMatch = -1;

        foreach ($directories as [$prefix, $length, $index]) {
            if ($length > $longestMatch && str_starts_with($path, (string) $prefix)) {
                $longestMatch = $length;
                $rank = $index;
            }
        }

        return $rank;
    }

    /**
     * Recursively find files under $searchPath matching a glob-style filename
     * pattern (e.g. star-dot-php), at any depth.
     *
     * Previously this used a single glob() call with an 8-level GLOB_BRACE
     * expansion (comma-separated star-slash alternatives) to fake recursion,
     * since glob() itself has no recursive mode. That pattern forced the C
     * glob() implementation to run up to 8 separate directory-tree scans from
     * the search root per call (one per brace alternative) and silently missed
     * anything nested more than 8 levels deep. A single RecursiveDirectoryIterator
     * pass walks the tree once, with no depth limit, and is both faster (one
     * tree walk instead of up to eight) and more correct.
     *
     * The walk's cost tracks entries *traversed*, not files matched, so the two
     * things that make it fast are not descending into trees that cannot hold a
     * resource ($this->prune) and not paying a stat() per entry to decide
     * whether to keep it (see the suffix fast path below). Measured against a
     * search path holding checked-out git repositories, pruning alone took the
     * walk from ~63ms to ~13ms for an identical match list - the .git
     * directories were two thirds of everything visited.
     *
     * @param string $searchPath
     * @param string $pattern
     * @return list<string> absolute paths of the matching files
     */
    protected function recursiveGlob(string $searchPath, string $pattern): array
    {
        $matches = [];

        // CURRENT_AS_PATHNAME hands back a plain string per entry instead of
        // constructing an SplFileInfo for it, which is what makes the stat-free
        // suffix test below possible
        $directory = new \RecursiveDirectoryIterator(
            $searchPath,
            \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::CURRENT_AS_PATHNAME
        );

        if ($this->prune !== []) {
            $prune = $this->prune;

            $directory = new \RecursiveCallbackFilterIterator(
                $directory,
                // rejecting a directory here prunes the whole subtree - the
                // iterator never descends into an entry the filter rejected.
                // Leaves are always accepted; the pattern test below is what
                // decides those, and re-testing them here would just duplicate it
                static fn($current, $key, $iterator): bool => !$iterator->hasChildren() || !in_array(basename((string)$current), $prune, true)
            );
        }

        $iterator = new \RecursiveIteratorIterator($directory, \RecursiveIteratorIterator::LEAVES_ONLY);

        // LEAVES_ONLY already excludes directories - hasChildren() makes them
        // containers, so they are never yielded as leaves - which means the
        // is-this-a-file question only needs answering for exotic entries
        // (dangling symlinks, sockets, fifos). Those used to be filtered by an
        // isFile() stat() on every entry in the tree; a resource directory
        // holding one is pathological, and a dangling view path still surfaces
        // as ViewNotFound when generate() checks file_exists() on it
        $suffix = $this->literalSuffix($pattern);

        if ($suffix !== null) {
            $suffixLength = strlen($suffix);

            foreach ($iterator as $path) {
                // an entry yielded as a leaf is a file, so its pathname ends
                // with the pattern's suffix exactly when its basename does
                if (substr_compare((string)$path, $suffix, -$suffixLength) === 0) {
                    $matches[] = (string)$path;
                }
            }
        } else {
            // patterns with metacharacters anywhere but a leading star still
            // need the real matcher
            foreach ($iterator as $path) {
                if (fnmatch($pattern, basename((string)$path))) {
                    $matches[] = (string)$path;
                }
            }
        }

        // glob() returns matches sorted alphabetically by default; match that so
        // insertion order (and therefore findFirst()/findLast() behavior) stays
        // the same regardless of filesystem directory-entry order
        sort($matches);

        return $matches;
    }

    /**
     * Reduce a glob pattern to the literal suffix every match must end with, or
     * null when the pattern needs a real glob matcher.
     *
     * Only the "leading star, literal tail" shape qualifies - which is the shape
     * every caller in the framework actually uses, because the pattern is always
     * built as '*.' . $extension. Anything else (a bare '*', an inner star, a
     * character class, a brace) falls back to fnmatch().
     *
     * @param string $pattern
     * @return string|null
     */
    protected function literalSuffix(string $pattern): ?string
    {
        if (!str_starts_with($pattern, '*')) {
            return null;
        }

        $suffix = substr($pattern, 1);

        // a bare '*' has no suffix to compare against - substr_compare() with a
        // zero-length needle does not return 0, so it cannot express "match all"
        if ($suffix === '') {
            return null;
        }

        // any remaining glob metacharacter means the tail is not literal
        return strpbrk($suffix, '*?[]{}\\') === false ? $suffix : null;
    }

    /**
     * add the matching resources
     *
     * @param string $searchPath
     * @param array<array-key, string> $matches
     * @return void
     * @throws NotFound
     */
    protected function addMatches(string $searchPath, array $matches): void
    {
        // hoisted out of the loop - it was re-read from the property per file
        $closureFunction = $this->keyClosure;

        foreach ($matches as $file) {
            $fileInfo = pathinfo((string) $file);
            $fileInfo['searchpath'] = $searchPath;
            // extract the key based on the function you chose
            $key = $closureFunction($fileInfo);
            // now add the resource
            $this->addResource($key, $file);
        }
    }

    /**
     * If the directory search locks after the first scan
     * and they then try to change it we need to throw an exception
     *
     * @return void
     * @throws ClassLocked
     */
    protected function ifLockedThrowException(): void
    {
        if ($this->locked) {
            throw new ClassLocked(self::class);
        }
    }

    /**
     * normalize the resource key
     *
     * This runs on every add/find/exists/remove call (not just at scan time), so
     * it's a hot path. mb_detect_encoding() runs a heuristic scan of the string on
     * every call to guess its encoding - resource keys are file/view names, which
     * are UTF-8 (or plain ASCII, a UTF-8 subset) in every realistic case, so a
     * fixed encoding is passed directly instead of re-detecting it each time.
     *
     * @param string $key
     * @return string
     */
    protected function normalizeKey(string $key): string
    {
        $newKey = ($this->normalizeKeys) ? mb_convert_case($key, MB_CASE_LOWER, 'UTF-8') : $key;

        return $this->hashKeys ? sha1($newKey, false) : $newKey;
    }

    /**
     *  Trigger a rescan on next read
     *
     * @return DirectorySearch
     */
    protected function rescan(): self
    {
        $this->rescan = true;

        return $this;
    }

    /**
     * register an additional callback function which
     * is called After most of the public functions
     *
     * @param string $action
     * @return DirectorySearch
     * @throws NotFound
     */
    protected function callback(string $action): self
    {
        // is a callback registered?
        if (!empty($this->callback)) {
            if (!is_object($this->callback[0]) || !method_exists($this->callback[0], $this->callback[1])) {
                throw new NotFound('Could not call Directory Search Callback ' . $action . ' because method ' . $this->callback[1] . ' does not exist on class ' . (is_object($this->callback[0]) ? $this->callback[0]::class : $this->callback[0]));
            }

            // call the callback and pass the action and this object
            // (as one array argument - that is the established signature)
            $callback = $this->callback;

            if (is_callable($callback)) {
                $callback([$action, $this]);
            }
        }

        return $this;
    }

    /**
     * Configure the resource key extraction strategy.
     *
     * The resource key style determines how filenames are translated into
     * resource keys (e.g., view paths, filenames, full paths, etc.).
     *
     * @param array<string, mixed> $config The configuration array, must include a 'resource key style' entry.
     *                      Can be a closure or one of the built-in style strings:
     *                      'filename', 'basename', 'fullpath', 'localpath', 'apppath', 'wwwpath', or 'view'.
     */
    protected function setupResourceKeyStyle(array $config): void
    {
        /*
        passed fileinfo
            fileInfo:
            dirname = "/home/johnnyAppleseed/Sites/orange/application/welcome/views/test"
            basename = "uploadForm.php"
            extension = "php"
            filename = "uploadForm"
            searchpath = "/home/johnnyAppleseed/Sites/orange/application/welcome/views"
        */

        // if they passed a closure use it
        if (is_closure($config['resource key style'])) {
            $this->keyClosure = $config['resource key style'];
        } else {
            // or use one of the built in resource key extractor based on the complete resource file path
            $this->keyClosure = match ($config['resource key style']) {
                // The key will be the filename ie. uploadForm
                'filename', 'config' => fn($fileInfo) => $fileInfo['filename'],
                // The key will be the basename ie. uploadForm.php
                'basename' => fn($fileInfo) => $fileInfo['basename'],
                // The key will be the dirname + basename ie. /home/johnnyAppleseed/Sites/orange/application/welcome/views/test/uploadForm.php
                'fullpath' => fn($fileInfo) => $fileInfo['dirname'] . DIRECTORY_SEPARATOR . $fileInfo['basename'],
                // The key will be the dirname + basename - the search path ie. test/uploadForm.php
                'localpath' => fn($fileInfo) => substr($fileInfo['dirname'] . DIRECTORY_SEPARATOR . $fileInfo['basename'], strlen($fileInfo['searchpath']) + 1),
                // The key will be the dirname + basename - the search path ie. /application/welcome/views/test/uploadForm.php
                'apppath' => fn($fileInfo) => substr($fileInfo['dirname'] . DIRECTORY_SEPARATOR . $fileInfo['basename'], strlen(__ROOT__)),
                // The key will be the dirname + basename - the search path ie. /application/welcome/views/test/uploadForm.php
                'wwwpath' => fn($fileInfo) => substr($fileInfo['dirname'] . DIRECTORY_SEPARATOR . $fileInfo['basename'], strlen(__WWW__)),
                // The key will be the dirname + basename - the search path - the extension ie. test/uploadForm
                default => fn($fileInfo) => substr($fileInfo['dirname'] . DIRECTORY_SEPARATOR . $fileInfo['basename'], strlen($fileInfo['searchpath']) + 1, -strlen($fileInfo['extension']) - 1),
            };
        }
    }
}
