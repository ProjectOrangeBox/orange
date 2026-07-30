<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

use orange\framework\property\RouterCallback;

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
