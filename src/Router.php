<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\base\Singleton;
use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\MissingRequired;
use orange\framework\exceptions\router\HttpMethodNotSupported;
use orange\framework\exceptions\router\RouteNotFound;
use orange\framework\exceptions\router\RouterNameNotFound;
use orange\framework\interfaces\CacheInterface;
use orange\framework\interfaces\InputInterface;
use orange\framework\interfaces\RouterInterface;
use orange\framework\property\RouterCallback;
use orange\framework\traits\ConfigurationTrait;

/**
 * Overview of Router.php
 *
 * This file defines the Router class in the orange\framework namespace.
 * It is a core component of the Orange framework responsible for managing application routes
 * — the mapping between URLs, HTTP methods, and their corresponding callbacks or controllers.
 * It implements both the Singleton pattern and the RouterInterface.
 *
 * ⸻
 *
 * 1. Core Purpose
 *  •   Registers routes (individually or in bulk).
 *  •   Matches incoming requests (URI + HTTP method) to defined routes.
 *  •   Provides named route lookups to generate URLs.
 *  •   Manages route configuration and caching for performance.
 *  •   Enforces routing rules and validates parameters.
 *
 * ⸻
 *
 * 2. Key Properties
 *  •   $routes → holds routes grouped by HTTP method (GET, POST, PUT, DELETE, etc.).
 *  •   $routesByName → stores routes keyed by their names for quick URL generation.
 *  •   $matched → stores details of the last matched route (URI, method, arguments, name, callback).
 *  •   $inputService → reference to InputInterface, used for request details (method, URI, HTTPS).
 *  •   $siteUrl → base URL of the application.
 *  •   $cacheService / $cacheKey → optional caching mechanism to persist routes.
 *  •   $onMatchAll → defines methods used for wildcard route matching.
 *  •   $skipParameterTypeChecking → flag to bypass regex validation of route parameters.
 *
 * ⸻
 *
 * 3. Constructor
 *  •   Takes in config, input service, and optional cache service.
 *  •   Validates required configuration (site).
 *  •   Loads routes from cache (if available) or from configuration.
 *  •   Sets up default route placeholders like 404 and home.
 *
 * ⸻
 *
 * 4. Core Methods
 *  1.  addRoute(array $options)
 *  •   Registers a single route (URL, method, callback, name).
 *  •   Supports wildcard methods (*) by mapping to multiple HTTP verbs.
 *  •   Updates cache.
 *  2.  addRoutes(array $routes)
 *  •   Registers multiple routes at once (reverse order for precedence).
 *  3.  match(string $requestUri, string $requestMethod)
 *  •   Matches an incoming URI + method to a defined route using regex.
 *  •   Populates $matched with details (URL, argv, callback, etc.).
 *  •   Throws RouteNotFound if nothing matches.
 *  4.  getMatched(?string $key = null)
 *  •   Returns full matched route info or a specific value (like callback or url).
 *  5.  getUrl(string $searchName, array $arguments = [], ?bool $skipParameterTypeChecking = null)
 *  •   Generates a URL from a named route, inserting arguments into placeholders.
 *  •   Enforces regex validation on arguments unless skipped.
 *  •   Throws exceptions if arguments don’t match or route name isn’t found.
 *  6.  siteUrl(bool|string $prefix = true)
 *  •   Returns the application’s base URL with optional scheme (http://, https://, or custom).
 *  7.  updateCache() / getRoutes() / addConfigRoutes()
 *  •   Manage loading and caching of route definitions for performance.
 *
 * ⸻
 *
 * 5. Error Handling
 *  •   Throws MissingRequired if critical config (like site) is missing.
 *  •   Throws RouteNotFound when no route matches.
 *  •   Throws HttpMethodNotSupported if an invalid method is used.
 *  •   Throws RouterNameNotFound if generating a URL for an unknown route.
 *  •   Throws InvalidValue for mismatched argument counts or regex validation failures.
 *
 * ⸻
 *
 * 6. Big Picture
 *
 * Router.php is the routing engine of the framework:
 *  1.  Developers register routes with methods, URLs, and callbacks.
 *  2.  Incoming requests are matched against these routes.
 *  3.  The router hands off the matched route details to the dispatcher, which calls the appropriate controller.
 *  4.  Optionally, caching makes repeated lookups faster.
 *
 * It ensures that every HTTP request is routed consistently, securely, and with flexible options
 * like named routes, parameter validation, and caching.
 *
 * @package orange\framework
 */
class Router extends Singleton implements RouterInterface
{
    // include ConfigurationTrait methods
    use ConfigurationTrait;

    // Base URL of the site, used for generating full URLs.
    protected string $siteUrl;

    // Routes by HTTP method
    protected array $routes = [
        'CONNECT' => [],
        'DELETE' => [],
        'GET' => [],
        'HEAD' => [],
        'OPTIONS' => [],
        'PATCH' => [],
        'POST' => [],
        'PUT' => [],
        'TRACE' => [],
    ];

    // Stores information about the last matched route.
    protected array $matched = [];

    // Determines whether URL validation during generation can be skipped.
    protected bool $skipParameterTypeChecking = false;

    // Array of routes sorted by the route name
    protected array $routesByName = [];

    // On Match All routes use these methods
    // e.g., ['GET', 'POST', 'PUT', 'DELETE']
    protected array $onMatchAll = [];

    // the caching key for routes
    protected string $cacheKey;
    // to turn off caching of routes
    protected bool $disableCaching = false;

    /**
     * Protected constructor to enforce Singleton usage.
     *
     * @param array $config Configuration array for routing settings.
     * @param InputInterface $inputService Provides request-related data.
     * @param CacheInterface|null $cacheService optional cache service
     * @throws MissingRequired If the 'site' configuration is missing.
     */
    protected function __construct(array $config, protected InputInterface $inputService, protected ?CacheInterface $cacheService = null)
    {
        logMsg('INFO', __METHOD__);

        // load the default configs
        $this->config = $this->mergeConfigWith($config, 'routes', false);

        // Set the site URL
        if (isset($this->config['site url'])) {
            $this->siteUrl = $this->config['site url'];
        } else {
            $this->siteUrl = $this->inputService->server('HTTP_HOST', '');
        }

        // let's make sure we set the site url
        if (empty($this->siteUrl)) {
            throw new MissingRequired('can\'t determine site url.');
        }

        // Set the skip parameter type checking flag
        $this->skipParameterTypeChecking = $this->config['skip parameter type checking'] ?? false;

        // Set the on match all methods
        $this->onMatchAll = $this->config['match all'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];

        // Set the cache if provided
        if ($this->cacheService) {
            // Set the cache key
            $this->cacheKey = ENVIRONMENT . '\\' . __CLASS__;
        }

        // Load the routes
        $this->getRoutes();

        // setup the "empty" matched
        $this->resetMatched();
    }

    /**
     * Reset $this->matched to its "no match yet" shape.
     *
     * Called from the constructor and at the start of match(): without resetting
     * here, a match() call that fails to find a route would leave the previous
     * successful match's data in place - the "every route has a url" falsy check in
     * match() would then see that stale truthy url, skip throwing RouteNotFound, and
     * silently leave a prior, unrelated request's routing data active. This matters
     * whenever the same Router instance handles more than one match() call - e.g. a
     * long-running worker process (Swoole/RoadRunner) reusing the singleton across
     * requests, not just the single-match-per-request classic PHP-FPM lifecycle.
     *
     * @return void
     */
    protected function resetMatched(): void
    {
        $this->matched = [
            'request method' => null,
            'request uri' => null,
            'matched uri' => null,
            'matched method' => null,
            'url' => null,
            'argv' => null,
            'argc' => 0,
            'args' => 0,
            'name' => null,
            'callback' => null,
        ];
    }

    /**
     * Adds a single route definition.
     *
     * @param array $options Route configuration (e.g., method, URL pattern, callback).
     * @return self
     */
    public function addRoute(array $options): self
    {
        logMsg('DEBUG', __METHOD__, $options);

        // can't do anything without a url
        if (isset($options['url'])) {
            // is this a http routable method?
            if (isset($options['method'])) {
                // is this the wildcard all an array or a single value
                $methods = $options['method'] == '*' ? $this->onMatchAll : (array)$options['method'];

                // for each method add it to the appropriate array for quicker access
                foreach ($methods as $method) {
                    $upperMethod = strtoupper($method);

                    if (!isset($this->routes[$upperMethod])) {
                        throw new HttpMethodNotSupported($method);
                    }

                    // FILO stack
                    array_unshift($this->routes[$upperMethod], $options);
                }
            }

            // does this route have a name to use with get url?
            if (isset($options['name'])) {
                // add it to the array by name
                $this->routesByName[mb_strtolower($options['name'])] = $options['url'];
            }
        }

        // Save the route to cache
        $this->updateCache();

        // return $ instance for method chaining
        return $this;
    }

    /**
     * Adds multiple routes in bulk.
     *
     * @param array $routes Array of route configurations.
     * @return self
     */
    public function addRoutes(array $routes): self
    {
        logMsg('INFO', __METHOD__);
        logMsg('INFO', 'Routes ' . count($routes));

        // disable cache temporarily to avoid writing to cache while adding routes
        $this->disableCaching = true;

        // Add each route in reverse order
        foreach (array_reverse($routes) as $route) {
            $this->addRoute($route);
        }
        // re-enable cache after adding routes
        $this->disableCaching = false;

        // save the routes to cache if available
        $this->updateCache();

        // return $ instance for method chaining
        return $this;
    }

    /**
     * Matches a request URI and method to a defined route.
     *
     * @param string $requestUri The request URI to match.
     * @param string $requestMethod The HTTP request method (e.g., GET, POST).
     * @return self
     * @throws RouteNotFound If no matching route is found.
     */
    public function match(string $requestUri, string $requestMethod): self
    {
        logMsg('DEBUG', __METHOD__, compact('requestUri', 'requestMethod'));

        // clear any previous match so a failed match here can never inherit a prior
        // successful match's (truthy) url and skip throwing RouteNotFound below
        $this->resetMatched();

        // Normalize the request method
        $requestMethodUpper = mb_strtoupper($requestMethod);

        // Check for matching routes
        foreach ($this->routes[$requestMethodUpper] ?? [] as $route) {
            // Check if the route matches the request URI
            if (preg_match("@^" . $route['url'] . "$@D", '/' . trim($requestUri, '/'), $argv)) {
                // Get the URL from the arguments
                $url = array_shift($argv);

                // decode each argument
                foreach ($argv as &$value) {
                    $value = urldecode($value);
                }

                // Set the matched route information
                $this->matched = [
                    'request method' => $requestMethodUpper,
                    'request uri' => $requestUri,
                    'matched uri' => $route['url'],
                    'matched method' => $route['method'],
                    'url' => $url,
                    'argv' => $argv,
                    'argc' => count($argv),
                    'args' => !empty($argv),
                    'name' => $route['name'] ?? null,
                    'callback' => $route['callback'] ?? null,
                ];

                logMsg('DEBUG', 'Route matched.', $this->matched);

                // we found a match break from foreach loop
                break;
            }
        }

        // every route has a url. (why else would you add it?)
        if (!$this->matched['url']) {
            throw new RouteNotFound("[$requestMethod] $requestUri");
        }

        return $this;
    }

    /**
     * Retrieves matched route information.
     *
     * @param string|null $key Specific key to retrieve (e.g., 'url', 'method').
     * @return mixed The value of the matched key or all matched data.
     * @throws InvalidValue If an invalid key is requested.
     */
    public function getMatched(?string $key = null): mixed /* mixed string|array */
    {
        logMsg('DEBUG', __METHOD__, ['key' => $key]);

        // Check if the key is valid
        if ($key != null && !\array_key_exists(mb_strtolower($key), $this->matched)) {
            throw new InvalidValue('Unknown routing value "' . $key . '"');
        }

        // Return the matched data
        return ($key) ? $this->matched[mb_strtolower($key)] : $this->matched;
    }

    public function getRouterCallback(): RouterCallback
    {
        $callback = $this->getMatched('callback');

        if (!is_array($callback) || !isset($callback[0]) || !isset($callback[1])) {
            throw new InvalidValue('Invalid route callback configuration.');
        }

        return new RouterCallback(
            controller: $callback[0],
            method: $callback[1],
            arguments: $this->getMatched('argv') ?? []
        );
    }

    /**
     * Generates a URL from a named route and arguments.
     *
     * @param string $searchName Route name.
     * @param array $arguments Arguments for dynamic segments.
     * @return string The generated URL.
     * @throws RouterNameNotFound If the route name is not found.
     */
    public function getUrl(string $searchName = '', array $arguments = [], ?bool $skipParameterTypeChecking = null): string
    {
        logMsg('INFO', __METHOD__ . ' ' . $searchName);
        logMsg('DEBUG', '', ['searchName' => $searchName, 'arguments' => $arguments, 'skipParameterTypeChecking' => $skipParameterTypeChecking]);

        // default to site url
        $matchedUrl = $this->siteUrl;

        // if they provided a name
        // then we need to look it up and do further processing
        if (!empty($searchName)) {
            // Normalize the search name
            $lowercaseSearchName = mb_strtolower($searchName);

            // Check if the route exists
            if (!isset($this->routesByName[$lowercaseSearchName])) {
                throw new RouterNameNotFound($searchName);
            }

            // let's begin
            $matchedUrl = $this->routesByName[$lowercaseSearchName];

            $matches = [];

            // merge the arguments with the available parameters
            $hasArgs = preg_match_all('/\((.*?)\)/m', $matchedUrl, $matches, PREG_SET_ORDER, 0);

            // do the number of arguments passed in match the number of arguments in the url?
            if (count($matches) != count($arguments)) {
                throw new InvalidValue('Parameter count mismatch. Expecting ' . count($matches) . ' got ' . count($arguments) . ' route named "' . $searchName . '".');
            }

            // does this url have any arguments?
            if ($hasArgs) {
                // Determine if we should skip parameter type checking
                $skipParameterTypeChecking = is_bool($skipParameterTypeChecking) ? $skipParameterTypeChecking : $this->skipParameterTypeChecking;
                // Get the URL matches
                foreach ($matches as $index => $match) {
                    // convert to a string
                    $value = (string)$arguments[$index];

                    // make sure the argument matches the regular expression for that segment
                    if (!$skipParameterTypeChecking && !preg_match('@' . $match[0] . '@m', $value)) {
                        throw new InvalidValue('Parameter mismatch. Expecting ' . $match[1] . ' got ' . $value);
                    }

                    // replace the segment with the passed argument
                    $matchedUrl = preg_replace('/' . preg_quote($match[0], '/') . '/', $value, $matchedUrl, 1);
                }
            }

            // is the matchedUrl now empty?
            if (empty($matchedUrl)) {
                throw new RouterNameNotFound($searchName);
            }
        }

        logMsg('INFO', __METHOD__ . ' matched Url ' . $matchedUrl);

        return $matchedUrl;
    }

    /**
     * Generates the site's base URL, optionally with an HTTP/HTTPS prefix.
     *
     * This method allows the caller to:
     * - Include or exclude the HTTP/HTTPS prefix.
     * - Manually specify a custom prefix.
     *
     * @param bool|string $prefix
     *      - `true`: Automatically determines `http` or `https` based on the request.
     *      - `false`: Returns only the base URL without any protocol prefix.
     *      - `string`: Allows specifying a custom protocol prefix (e.g., `'ftp://'`).
     *
     * @return string The generated base URL with the specified prefix.
     */
    public function siteUrl(bool|string $prefix = true): string
    {
        // Determine the scheme
        if (is_string($prefix)) {
            // Use the custom prefix
            $scheme = $prefix;
        } else {
            // Auto determine the scheme based on the request
            $scheme = ($this->inputService->isHttpsRequest() ? 'https://' : 'http://');
        }

        // Build the site URL
        return $prefix ? $scheme . $this->siteUrl : $this->siteUrl;
    }

    /**
     * Saves the current routes to the cache if cache service provided.
     * This method serializes the routes and routesByName arrays
     *
     * @return void
     */
    protected function updateCache()
    {
        // Check if the cache is available and caching is not disabled
        if ($this->cacheService && !$this->disableCaching) {
            // Cache the current routes
            $this->cacheService->set($this->cacheKey, ['routes' => $this->routes, 'routesByName' => $this->routesByName]);
        }
    }

    /**
     * Loads routes from the cache or configuration.
     * If the cache is not available or empty, it will load routes from the configuration.
     * If the cache is available, it will check for cached routes and use them if valid.
     * If no cached routes are found, it will load the configuration routes and cache them.
     *
     * @return void
     * @throws MissingRequired
     */
    protected function getRoutes(): void
    {
        // Check if the cache is available
        if ($this->cacheService) {
            // try to load the cached routes
            $cachedRoutes = $this->cacheService->get($this->cacheKey);

            // if we get anything but a array we assume cache is invalid
            if (!is_array($cachedRoutes) || !isset($cachedRoutes['routes']) || !isset($cachedRoutes['routesByName'])) {
                // didn't find them so force a load and then set the cache
                $this->addConfigRoutes();
            } else {
                // cache is a array so we can assume it is valid
                $this->routes = $cachedRoutes['routes'];
                $this->routesByName = $cachedRoutes['routesByName'];
            }
        } else {
            // no cache being used so load the routes
            $this->addConfigRoutes();
        }
    }

    /**
     * Adds routes from the configuration.
     *
     * @throws MissingRequired If the configuration does not contain the required routes.
     *
     * @return void
     */
    protected function addConfigRoutes(): void
    {
        // turn off caching for addRoute(...)
        // addRoutes(...) will turn it back on and cache before exiting
        $this->disableCaching = true;

        // add 404 first which makes it the last in the search
        // add our default home - this could get overwritten by another home
        // add the user supplied routes
        $this->addRoute($this->config['404'])->addRoute($this->config['home'])->addRoutes($this->config['routes']);
    }
}
