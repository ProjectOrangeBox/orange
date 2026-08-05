<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

use orange\framework\property\RouterCallback;

/**
 * Matches a request to a route, and resolves route names back to URLs.
 *
 * Those are two directions of one table and both belong here. match() runs once
 * per request and leaves the result on the instance, which is why it returns
 * $this rather than the route: getMatched() then reads any field of it, and
 * getRouterCallback() packages the part the dispatcher needs into a
 * RouterCallback. Calling either before match() is a programming error, not a
 * miss.
 *
 * The other direction is getUrl(), and it is the one application code touches.
 * A route's url is a regex; capture groups become the $arguments substituted
 * back in. Resolving a name is how a path is meant to be written - a hardcoded
 * '/blog/post/4' silently survives the route being renamed, where getUrl() does
 * not:
 *
 *   $router->getUrl('blogPost', [4]);   // '/blog/post/4'
 *
 * A route needs only url and name to be resolvable this way. Entries with no
 * callback are not routable at all and exist purely so that asset paths and the
 * like have one place to change - see the assets/javascript/css/images entries
 * in the routes config.
 */
interface RouterInterface
{
    public function match(string $requestUri, string $requestMethod): self;
    public function getMatched(?string $key = null): mixed; /* mixed string|array */
    public function getRouterCallback(): RouterCallback;
    /**
     * @param array<array-key, mixed> $arguments substituted into the route url
     */
    public function getUrl(string $searchName, array $arguments = []): string;
    public function siteUrl(bool|string $appendHttp = true): string;
    /**
     * @param array<string, mixed> $route
     */
    public function addRoute(array $route): self;
    /**
     * @param array<array-key, array<string, mixed>> $routes
     */
    public function addRoutes(array $routes): self;
}
