<?php

declare(strict_types=1);

namespace orange\framework\helpers;

use Closure;
use orange\framework\exceptions\NotFound;
use orange\framework\exceptions\ClassLocked;
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
    protected array $directories = [];

    // array of "found" files
    protected array $resources = [];

    // set of resource keys matching more than one file - the only ones whose order
    // can be wrong, so a rescan sorts these instead of walking every resource.
    // Stale entries are harmless: the count check in the sort skips them.
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
    protected array $callback = [];

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
     * @param array $config Configuration array for the DirectorySearch instance.
     */
    public function __construct(array $config)
    {
        $config = array_replace($this->defaults, $config);

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
     * @param array $directories
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
     * @param array $directories
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
     * @return array
     */
    public function listDirectories(): array
    {
        return array_keys($this->directories);
    }

    /**
     * replace all directories
     *
     * @param array $directories
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
        return array_key_exists(realpath(rtrim($directory, DIRECTORY_SEPARATOR)), $this->directories);
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
     * @param array $resources
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
     * @param array $resources
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
     * @param array $resources
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
     * @return array
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
     * @return array
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
     * @return array
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
     * @return array{resources: array, directories: array}
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
                    if ($this->recursive) {
                        $this->addMatches($searchPath, $this->recursiveGlob($searchPath, $this->match));
                    } else {
                        $this->addMatches($searchPath, glob($searchPath . '/' . $this->match));
                    }
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

        // $this->directories is already in priority order, so its index is the rank
        $directories = array_keys($this->directories);

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
     * @param array $directories registered directories, already in priority order
     * @return int
     */
    protected function directoryRank(string $path, array $directories): int
    {
        $rank = PHP_INT_MAX;
        $longestMatch = -1;

        foreach ($directories as $index => $directory) {
            $length = strlen((string)$directory);

            if ($length > $longestMatch && str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
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
     * @param string $searchPath
     * @param string $pattern
     * @return array
     */
    protected function recursiveGlob(string $searchPath, string $pattern): array
    {
        $matches = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($searchPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
                $matches[] = $file->getPathname();
            }
        }

        // glob() returns matches sorted alphabetically by default; match that so
        // insertion order (and therefore findFirst()/findLast() behavior) stays
        // the same regardless of filesystem directory-entry order
        sort($matches);

        return $matches;
    }

    /**
     * add the matching resources
     *
     * @param string $searchPath
     * @param array|false $matches
     * @return void
     * @throws NotFound
     */
    protected function addMatches(string $searchPath, array|false $matches): void
    {
        if (is_array($matches)) {
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
            call_user_func($this->callback, [$action, $this]);
        }

        return $this;
    }

    /**
     * Configure the resource key extraction strategy.
     *
     * The resource key style determines how filenames are translated into
     * resource keys (e.g., view paths, filenames, full paths, etc.).
     *
     * @param array $config The configuration array, must include a 'resource key style' entry.
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
