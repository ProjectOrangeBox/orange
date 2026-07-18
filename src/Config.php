<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\base\SingletonArrayObject;
use orange\framework\interfaces\CacheInterface;
use orange\framework\interfaces\ConfigInterface;
use orange\framework\exceptions\filesystem\DirectoryNotFound;
use orange\framework\exceptions\config\InvalidConfigurationValue;
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
 *  •   Gives developers array-style access (via ArrayObject) as well as method access.
 *
 * ⸻
 *
 * 2. Key Properties
 *  •   $configuration → stores loaded configurations indexed by filename.
 *  •   $searchDirectories → list of directories where configuration files will be searched.
 *  •   $foundDirectoriesByName → map of config file names to their discovered file paths across directories.
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
class Config extends SingletonArrayObject implements ConfigInterface
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

    protected string $separator = '.';

    /**
     * Protected constructor to enforce Singleton usage.
     *
     * @param array $config Initial configuration array.
     * @throws DirectoryNotFound If the default configuration directory is invalid.
     */
    protected function __construct(array $config = [], ?CacheInterface $cacheService = null)
    {
        logMsg('INFO', __METHOD__);

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
        logMsg('INFO', __METHOD__ . ' ' . $filename);

        // isset() should be a cheap, side-effect-free existence check: don't call
        // load() here - it would parse/merge the config file (and can throw
        // ConfigFileDidNotReturnAnArray) just to answer an isset(). A filename with no
        // discovered files at all isn't in $foundConfigFiles, and count(null) is a
        // TypeError under PHP 8, so guard with ?? [].
        return count($this->foundConfigFiles[$filename] ?? []) > 0;
    }

    /**
     * Retrieve configuration content for a specific file.
     *
     * @param mixed $filename Name of the configuration file.
     * @return mixed Configuration data.
     * @throws InvalidConfigurationValue If configuration data is invalid.
     */
    public function offsetGet(mixed $filename): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $filename);

        return $this->get($filename);
    }

    /**
     * Retrieve configuration data by filename and optional key.
     *
     * @param string $filenameKey Config filename, optionally followed by a dotted key (e.g. "app.debug").
     * @param mixed $defaultValue Default value if the key does not exist.
     * @return mixed Configuration value or default value if key not found.
     */
    #[\Override]
    public function get(string $filenameKey, mixed $defaultValue = null): mixed
    {
        logMsg('INFO', __METHOD__ . ' ' . $filenameKey);

        $filename = $filenameKey;
        $key = null;

        if (str_contains($filenameKey, $this->separator)) {
            [$filename, $key] = explode($this->separator, $filenameKey, 2);
        }

        // Load the configuration file
        $completeConfig = $this->load($filename);

        // Return the entire array if no key is specified
        return $key !== null ? ($completeConfig[$key] ?? $defaultValue) : $completeConfig;
    }

    /**
     * Load a configuration file into memory.
     *
     * @param string $filename Name of the configuration file.
     * @return array The configuration array.
     * @throws InvalidConfigurationValue If the configuration file doesn't return an array.
     */
    protected function load(string $filename): array
    {
        $config = [];

        // Check if the configuration file exists in the found directories
        if (isset($this->foundConfigFiles[$filename])) {
            // Check if configuration has already been loaded
            if (!isset($this->configuration[$filename])) {
                $foundConfigs = [];

                foreach ($this->foundConfigFiles[$filename] as $configFile) {
                    if (!is_array($includedConfig = include $configFile)) {
                        throw new ConfigFileDidNotReturnAnArray('"' . $configFile . '" did not return an array.');
                    }

                    $foundConfigs[] = $includedConfig;
                }

                // now let's do the merge all at once.
                $this->configuration[$filename] = array_replace_recursive(...$foundConfigs);
            }

            $config = $this->configuration[$filename];
        }

        // and now configuration has the configuration array
        return $config;
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
            foreach (glob($searchDirectory . DIRECTORY_SEPARATOR . '*.php') as $file) {
                $name = basename($file, '.php');
                // if we haven't added this config then we need to add it now.
                if (!isset($found[$name])) {
                    $found[$name] = [];
                }
                // get canonicalized absolute pathname
                $found[$name][] = realpath($file);
            }
        }

        return $found;
    }
}
