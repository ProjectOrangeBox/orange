# Reference: Router & Dispatcher

[← Reference index](README.md) · Guide: [Routing](../04-routing.md)

## Router

`orange\framework\Router implements RouterInterface` — the route table keyed by HTTP verb.
Singleton, built from the `routes` config and the `input` service (with an optional cache
service). Reach it via `container()->router`.

### `match(string $requestUri, string $requestMethod): self`

Match a request URI + method against the registered routes, storing the result. Normalises the URI
to `'/' . trim($uri, '/')`, upper‑cases the method, and tests each route for that method: literal
URLs by string equality, pattern URLs by `preg_match("@^{$url}$@D", …)`. Captured groups become the
route arguments (URL‑decoded). Called for you by the lifecycle.

- **Throws** `RouteNotFound` if nothing matches.

### `getMatched(?string $key = null): mixed`

The matched route data. With no key, the whole array: `request method`, `request uri`,
`matched uri`, `matched method`, `url`, `argv`, `argc`, `args`, `name`, `callback`. With a key,
that entry.

- **Throws** `InvalidValue` for an unknown key.

### `getRouterCallback(): RouterCallback`

Build a `RouterCallback` value object (`controller`, `method`, `arguments`) from the matched
route's callback, for the dispatcher.

- **Throws** `InvalidValue` if the callback isn't a valid `[controller, method]` pair.

### `getUrl(string $searchName = '', array $arguments = [], ?bool $skipParameterTypeChecking = null): string`

Reverse‑generate a URL from a route name. Substitutes `$arguments` into the URL's capture groups in
order. Requires the argument count to equal the group count, and by default validates each argument
against its group's regex. Pass `true` (or set `skip parameter type checking` in config) to skip
validation. With an empty name, returns the site URL.

- **Throws** `RouterNameNotFound` (unknown name or empty result); `InvalidValue` (count mismatch or
  a value failing its regex).

```php
getUrl('rest_read', [42]);        // '/api/read/42'
getUrl('rest_read', ['x']);       // throws InvalidValue — 'x' fails (\d+)
container()->router->getUrl('home');   // '/'
```

### `siteUrl(bool|string $prefix = true): string`

The application base URL. `true` → auto `http`/`https` prefix (from the request); `false` → no
prefix; a string → that custom prefix (e.g. `'ftp://'`).

### `addRoute(array $route): self` / `addRoutes(array $routes): self`

Register a route (or many) at runtime. A route array has `method`, `url`, optional `callback`,
optional `name`. `'*'` for method expands to the configured `match all` list.

- **Throws** `HttpMethodNotSupported` for an unknown HTTP method.

### Configuration (`routes` config)

| Key | Default | Purpose |
|-----|---------|---------|
| `routes` | `[]` | Route definitions (usually from your `routes.php`). |
| `match all` | `['GET','POST','PUT','DELETE','PATCH']` | Methods `'*'` expands to. |
| `skip parameter type checking` | `false` | Default for `getUrl()` argument validation. |
| `404` | catch‑all `(.*)` → `FourohfourController::index` | Not‑found route. |
| `home` | `/` → `HomeController::index` | Homepage route. |

---

## Dispatcher

`orange\framework\Dispatcher implements DispatcherInterface` — executes a matched route. Singleton.

### `call(RouterCallback $routerCallback): string`

Verify the controller class and method exist and are public, instantiate the controller, invoke
the method with the route's captured arguments, and return the result — which **must be a string**.

- **Throws** `ControllerClassNotFound`, `MethodNotFound`, `ArgumentMissMatch`, or `InvalidValue`
  (non‑string return).

### `RouterCallback`

`orange\framework\property\RouterCallback` — a plain value object:

```php
new RouterCallback(
    public string $controller,   // fully-qualified controller class
    public string $method,       // method name
    public array  $arguments,    // captured URL arguments, in order
);
```

Produced by `Router::getRouterCallback()`, consumed by `Dispatcher::call()`.

---

[← Reference index](README.md) · Guide: [Routing](../04-routing.md)
