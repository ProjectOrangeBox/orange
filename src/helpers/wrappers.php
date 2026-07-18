<?php

declare(strict_types=1);

/*
 * This is the easiest way to get the container instance
 * which is attached to the Application Class
 */

if (!function_exists('container')) {
    function container(): orange\framework\interfaces\ContainerInterface
    {
        // wrapper for...
        return \orange\framework\Container::getInstance();
    }
}

/*
 * Easy Access to logging
 * works only if logging service exists
 *
 * override as needed
 */
if (!function_exists('logMsg')) {
    function logMsg(mixed $level, string $msg, array $context = []): void
    {
        static $logInstance;

        try {
            // keep a static reference to the log instance to avoid multiple container calls
            if ($logInstance === null) {
                $logInstance = container()->log;
            }

            $logInstance->log($level, $msg, $context);
        } catch (Throwable) {
            // good chance the container or log isn't setup yet
            // so we can't do anything yet
        }
    }
}

/* wrapper to read a config value */
if (!function_exists('config')) {
    function config(?string $filename = null, ?string $key = null, mixed $default = null): mixed
    {
        static $configInstance;

        try {
            if ($configInstance === null) {
                // container()->config already IS the Config service - not a container
                // holding a nested "config" service
                $configInstance = container()->config;
            }

            if ($filename === null) {
                // no filename given - return the whole config service so the caller can
                // chain ->get()/->someFile themselves
                $config = $configInstance;
            } elseif ($key === null) {
                // filename only - return the entire config file as an array
                $config = $configInstance->get($filename, $default);
            } else {
                // filename + key
                $config = $configInstance->get($filename . '.' . $key, $default);
            }
        } catch (Throwable) {
            // config not setup?
            // fallback to default
            $config = $default;
        }

        return $config;
    }
}

/* wrapper for router get url */
if (!function_exists('getUrl')) {
    function getUrl(string $searchName = '', array $arguments = [], ?bool $skipCheckingType = null): string
    {
        // throws an exception if the router service isn't setup
        return container()->router->getUrl($searchName, $arguments, $skipCheckingType);
    }
}

/* wrapper for input */
if (!function_exists('input')) {
    function input(): \orange\framework\interfaces\InputInterface
    {
        return container()->input;
    }
}

/* wrapper for output */
if (!function_exists('output')) {
    function output(): \orange\framework\interfaces\OutputInterface
    {
        return container()->output;
    }
}

/* wrapper for applications env() method */
if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return \orange\framework\Application::get()->env($key, $default);
    }
}
