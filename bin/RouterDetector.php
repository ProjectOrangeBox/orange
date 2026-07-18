<?php

declare(strict_types=1);

namespace config;

use ReflectionClass;
use ReflectionMethod;
use orange\framework\attributes\Route;

/**
 * ONLY USE IN DEVELOPMENT
 */

class RouterDetector
{
    /**
     * we will use this class to scan the application for route attributes
     * and build the routes array that is used in the Router class
     *
     * @param array $paths
     * @return array
     */
    public static function detect(array $paths, array $routes = []): array
    {
        if (ENVIRONMENT != 'development') {
            die('The ' . __CLASS__ . ' should only be used in development. You can use the static method export to get the current array');
        }

        foreach ($paths as $path) {
            // we need to recursively scan the directory for php files
            foreach (static::rglob($path, '*.php') as $file) {
                // we need to scan the file for route attributes
                static::scan($routes, $file);
            }
        }

        return $routes;
    }

    /**
     * echo the formatted routes array
     *
     * @param array $paths
     * @return void
     */
    public static function export(array $paths): void
    {
        // we will just echo the formatted routes array
        echo static::format(static::detect($paths));
    }

    protected static function scan(array &$routes, string $file): void
    {
        $fullyQualifiedClass = static::getFullyQualifiedClass($file);

        if (!empty($fullyQualifiedClass)) {
            $reflectionClass = new ReflectionClass($fullyQualifiedClass);

            foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $reflectionMethod) {
                $attributes = $reflectionMethod->getAttributes(Route::class);

                if (!empty($attributes)) {
                    $routeInstance = $attributes[0]->newInstance();

                    $route['url'] = $routeInstance->url;
                    $route['name'] = $routeInstance->name;
                    $route['method'] = $routeInstance->method;

                    // remove empty values
                    $route = array_filter($route);

                    // only add if we have a valid route
                    if (!empty($route)) {
                        $route['callback'] = [$fullyQualifiedClass, $reflectionMethod->getName()];

                        $routes[] = $route;
                    }
                }
            }
        }
    }

    /**
     * we need to format the routes array into a string that can be used in the export method
     *
     * @param array $routes
     * @return string
     */
    protected static function format(array $routes): string
    {
        $output = '';
        $t = chr(39);

        // ['method' => '*', 'url' => '/', 'callback' => [\orange\framework\controllers\HomeController::class, 'index'], 'name' => 'home'],
        foreach ($routes as $route) {
            $line = '';

            if (isset($route['method'])) {
                $line .= $t . 'method' . $t . ' => ';

                if (is_array($route['method'])) {
                    $line .= '[';

                    foreach ($route['method'] as $m) {
                        $line .= $t . $m . $t . ',';
                    }

                    $line = rtrim($line, ',');

                    $line .= ']';
                } else {
                    $line .= $t . $route['method'] . $t;
                }

                $line .= ', ';
            }

            if (isset($route['url'])) {
                $line .= $t . 'url' . $t . ' => ' . $t . $route['url'] . $t . ', ';
            }

            if (isset($route['callback'])) {
                $line .= $t . 'callback' . $t . ' => [';

                $line .= $route['callback'][0] . '::class, ' . $t . $route['callback'][1] . $t;

                $line .= '], ';
            }

            if (isset($route['name'])) {
                $line .= $t . 'name' . $t . ' => ' . $t . $route['name'] . $t;
            }

            $line = trim($line, ', ');

            $output .= '[' . $line . '],' . PHP_EOL;
        }

        return '[' . PHP_EOL . $output . '],' . PHP_EOL;
    }

    protected static function getFullyQualifiedClass(string $file): string
    {
        // we need to keep track of the current namespace and class so we can build the callback
        $fullyQualifiedClass = '';
        $namespace = '';

        // we need to read the file into an array of lines
        foreach (file($file) as $line) {
            // we need to trim the line to remove any leading or trailing whitespace
            $line = trim($line);

            if (!empty($line)) {
                // we are looking for the namespace
                if (preg_match('/namespace\s+(.*)\s*;/', $line, $matches, PREG_OFFSET_CAPTURE, 0)) {
                    $namespace = $matches[1][0];
                }
                // we are looking for the class
                if (preg_match('/class\s*([^ ]*).*/', $line, $matches, PREG_OFFSET_CAPTURE, 0)) {
                    if (!empty($namespace)) {
                        $fullyQualifiedClass = chr(92) . $namespace . chr(92) . $matches[1][0];
                    }
                    break;
                }
            }
        }

        return $fullyQualifiedClass;
    }

    protected static function rglob(string $path, string $pattern): array
    {
        $paths = glob($path . '/*', GLOB_MARK | GLOB_ONLYDIR | GLOB_NOSORT);
        $files = glob($path . '/' . $pattern);

        if (!is_array($files)) {
            $files = [];
        }

        foreach ($paths as $subpath) {
            $files = array_merge($files, static::rglob($subpath, $pattern));
        }

        return $files;
    }
}
