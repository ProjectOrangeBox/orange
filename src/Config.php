<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\base\Singleton;
use orange\framework\interfaces\CacheInterface;
use orange\framework\interfaces\ConfigInterface;
use orange\framework\exceptions\config\ImmutableAccess;
use orange\framework\exceptions\config\ConfigFileDidNotReturnAnArray;

/**
 * Overview of Config.php
 *
 * This file defines the Config class in the orange\framework namespace.
 * It is the central configuration manager for the framework, responsible for loading,
 * merging, and serving configuration files in a structured way.
 * It follows the Singleton pattern, ensuring there is only one configuration instance shared across the application.
 *
 * ⸻
 *
 * 1. Core Purpose
 *  •   Provides a unified way to load configuration files.
 *  •   Supports multiple directories (with priority order).
 *  •   Allows environment-specific overrides.
 *  •   Implements caching of config metadata for performance.
 *  •   Gives developers read-only array-style access (via ArrayAccess) as well as method access;
 *      offsetSet()/offsetUnset() throw, since a loaded config file is immutable for the life of the request.
 *
 * ⸻
 *
 * 2. Key Properties
 *  •   $configuration → stores loaded configurations indexed by filename.
 *  •   $searchDirectories → list of directories where configuration files will be searched.
 *  •   $foundConfigFiles → map of config file names to their discovered file paths across directories.
 *  •   $resolved / $missingKeys → per-lookup memoization for get() ("file.key" → value / known-miss).
 *
 * ⸻
 *
 * 3. Initialization
 *  •   Constructor is protected (Singleton enforced).
 *  •   Accepts:
 *  •   $config → array of directories to search.
 *  •   $cacheService → optional cache service implementing CacheInterface.
 *  •   If caching is enabled:
 *  •   Tries to load cached map of config files.
 *  •   If cache is missing, builds the map and stores it.
 *  •   If no cache service, always builds the map fresh.
 *
 * ⸻
 *
 * 4. Configuration Loading
 *  •   load($filename)
 *  •   Finds all files with that name (e.g., database.php) across directories.
 *  •   Includes them, ensuring each returns an array.
 *  •   Merges them using array_replace_recursive() (later directories override earlier ones).
 *  •   Stores result in $configuration[$filename].
 *  •   Error handling
 *  •   Throws ConfigFileDidNotReturnAnArray if included file does not return an array.
 *
 * ⸻
 *
 * 5. Access Methods
 *  •   Magic getter (__get) → $config->database fetches the whole database.php array.
 *  •   offsetExists() → array-style access to checks if a config file exists.
 *  •   offsetGet() → array-style access to config file ($config['database']).
 *  •   offsetSet() / offsetUnset() → always throw ImmutableAccess; Config is read-only.
 *  •   get($filename, $key = null, $default = null)
 *  •   Fetches entire config file (if $key is null).
 *  •   Fetches specific key with fallback default.
 *
 * ⸻
 *
 * 6. Support Methods
 *  •   buildArray() → scans all search directories for *.php config files and builds an index.
 *  •   Uses glob() to find files.
 *  •   Returns array like:
 *       [
 *       'database' => ['/path/to/config/database.php', '/path/to/env/database.php'],
 *       'app' => ['/path/to/config/app.php']
 *       ]
 *
 * 7. Big Picture
 *  •   Config.php is the backbone for configuration management in the framework.
 *  •   It ensures configs are:
 *  •   Centralized
 *  •   Overridable by environment
 *  •   Efficiently merged and cached
 *  •   Provides flexible access ($config->file, $config['file'], $config->get('file.key')).
 *
 * @package orange\framework
 */
class Config extends Singleton implements ConfigInterface, \ArrayAccess
{
    /**
     * Stores loaded configurations indexed by filename.
     */
    protected array $configuration = [];

    /**
     * Array of directories to search for configuration files, in order of priority.
     */
    protected array $searchDirectories = [];

    /*
     * Map of config file names to their discovered file paths across directories.
     * Example:
     * [
     *   'database' => ['/path/to/config/database.php', '/path/to/env/database.php'],
     *   'app' => ['/path/to/config/app.php']
     * ]
     */
    protected array $foundConfigFiles = [];

    /**
     * Memoized get() results keyed by the full "$filenameKey" string.
     * Only successful lookups are stored here; values are never null, so a
     * cheap isset() is a valid hit test.
     */
    protected array $resolved = [];

    /**
     * Lookups known to have no value ("file.key" → true). Tracked separately
     * from $resolved because each caller supplies its own default value.
     */
    protected array $missingKeys = [];

    protected string $separator = '.';

    /**
     * Protected constructor to enforce Singleton usage.
     *
     * @param array $config Initial configuration array.
     * @param CacheInterface|null $cacheService Optional cache used to persist the discovered
     *        config-file map (keyed by ENVIRONMENT + class name) instead of rescanning the
     *        search directories on every instantiation.
     */
    protected function __construct(array $config = [], ?CacheInterface $cacheService = null)
    {
        logMsg('DEBUG', __METHOD__);

        $this->searchDirectories = $config['config directories'] ?? [];
        $this->separator = $config['config separator'] ?? $this->separator;

        if ($cacheService) {
            // cache key
            $cacheKey = ENVIRONMENT . '\\' . self::class;

            if ($cached = $cacheService->get($cacheKey)) {
                $this->foundConfigFiles = $cached;
            } else {
                $cacheService->set($cacheKey, $this->foundConfigFiles = $this->findAllConfigFilesInEachDirectory());
            }
        } else {
            $this->foundConfigFiles = $this->findAllConfigFilesInEachDirectory();
        }
    }

    /**
     * Magic getter to retrieve configuration for a specific filename.
     *
     * @param string $filename Name of the configuration file (without extension).
     * @return mixed Configuration data or null if not found.
     * @throws ConfigFileDidNotReturnAnArray If a discovered config file does not return an array.
     */
    #[\Override]
    public function __get(string $filename): mixed
    {
        return $this->get($filename);
    }

    /**
     * Check if a configuration file exists.
     *
     * @param mixed $filename Name of the configuration file.
     * @return bool True if the configuration file exists, false otherwise.
     */
    public function offsetExists(mixed $filename): bool
    {
        // isset() should be a cheap, side-effect-free existence check: don't call
        // load() here - it would parse/merge the config file (and can throw
        // ConfigFileDidNotReturnAnArray) just to answer an isset().
        return !empty($this->foundConfigFiles[$filename]);
    }

    /**
     * Retrieve configuration content for a specific file.
     *
     * @param mixed $filename Name of the configuration file.
     * @return mixed Configuration data.
     * @throws ConfigFileDidNotReturnAnArray If a discovered config file does not return an array.
     */
    public function offsetGet(mixed $filename): mixed
    {
        return $this->get($filename);
    }

    /**
     * Config is an immutable, loaded-once snapshot - it never accepts writes.
     *
     * @param mixed $filename
     * @param mixed $value
     * @return never
     * @throws ImmutableAccess Always.
     */
    public function offsetSet(mixed $filename, mixed $value): never
    {
        throw new ImmutableAccess('cannot set "' . $filename . '" - Config is read-only');
    }

    /**
     * Config is an immutable, loaded-once snapshot - it never accepts writes.
     *
     * @param mixed $filename
     * @return never
     * @throws ImmutableAccess Always.
     */
    public function offsetUnset(mixed $filename): never
    {
        throw new ImmutableAccess('cannot unset "' . $filename . '" - Config is read-only');
    }

    /**
     * Retrieve configuration data by filename and optional key.
     *
     * @param string $filenameKey Config filename, optionally followed by a dotted key (e.g. "app.debug").
     * @param mixed $defaultValue Default value if the key does not exist.
     * @return mixed Configuration value or default value if key not found.
     * @throws ConfigFileDidNotReturnAnArray If a discovered config file does not return an array.
     */
    #[\Override]
    public function get(string $filenameKey, mixed $defaultValue = null): mixed
    {
        // memoized hit - skips the separator parse and load() entirely
        if (isset($this->resolved[$filenameKey])) {
            return $this->resolved[$filenameKey];
        }

        // known miss - the default belongs to each caller, so only the miss is cached
        if (isset($this->missingKeys[$filenameKey])) {
            return $defaultValue;
        }

        $filename = $filenameKey;
        $key = null;

        if (str_contains($filenameKey, $this->separator)) {
            [$filename, $key] = explode($this->separator, $filenameKey, 2);
        }

        // Load the configuration file
        $completeConfig = $this->load($filename);

        // Return the entire array if no key is specified
        if ($key === null) {
            return $this->resolved[$filenameKey] = $completeConfig;
        }

        // isset() (not array_key_exists) on purpose: a key holding null falls
        // through to the default, matching the original "?? $defaultValue" behavior
        if (isset($completeConfig[$key])) {
            return $this->resolved[$filenameKey] = $completeConfig[$key];
        }

        $this->missingKeys[$filenameKey] = true;

        return $defaultValue;
    }

    /**
     * Load a configuration file into memory.
     *
     * @param string $filename Name of the configuration file.
     * @return array The configuration array.
     * @throws ConfigFileDidNotReturnAnArray If the configuration file doesn't return an array.
     */
    protected function load(string $filename): array
    {
        // Check if configuration has already been loaded
        if (isset($this->configuration[$filename])) {
            return $this->configuration[$filename];
        }

        $foundConfigs = [];

        foreach ($this->foundConfigFiles[$filename] ?? [] as $configFile) {
            if (!is_array($includedConfig = include $configFile)) {
                throw new ConfigFileDidNotReturnAnArray('"' . $configFile . '" did not return an array.');
            }

            $foundConfigs[] = $includedConfig;
        }

        // merge only when more than one file was found - the single-file case
        // (the common one) doesn't need array_replace_recursive() at all
        return $this->configuration[$filename] = isset($foundConfigs[1])
            ? array_replace_recursive(...$foundConfigs)
            : ($foundConfigs[0] ?? []);
    }

    /**
     * Find configuration files by name across search directories.
     * In production this can be cached.
     *
     * @return array
     */
    protected function findAllConfigFilesInEachDirectory(): array
    {
        $found = [];

        // find all of the cache file names by reading all of the searchDirectories
        foreach ($this->searchDirectories as $searchDirectory) {
            // canonicalize the directory once instead of realpath()ing every file
            // inside it (one syscall per directory instead of one per file);
            // a false return also skips directories that don't exist
            if (($realDirectory = realpath($searchDirectory)) === false) {
                continue;
            }

            // GLOB_NOSORT: order within a directory can't matter - a basename
            // appears at most once per directory, and merge priority comes from
            // the searchDirectories order
            foreach (glob($realDirectory . DIRECTORY_SEPARATOR . '*.php', GLOB_NOSORT) as $file) {
                $found[basename($file, '.php')][] = $file;
            }
        }

        return $found;
    }
}
