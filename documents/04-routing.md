# 4. Routing

[← The request lifecycle](03-request-lifecycle.md) · [Manual index](README.md) · [Next: Controllers →](05-controllers.md)

Routing maps an incoming HTTP method + URL to a controller method. Orange supports two mechanisms
that work together: **`#[Route]` attributes** on controller methods (the style you will use most)
and a plain **`routes.php`** array. In development the framework builds the route table from your
attributes automatically; for production you generate that table once and ship it.

## Routes with the `#[Route]` attribute

Put a `#[Route]` attribute on any public controller method:

```php
use orange\framework\attributes\Route;

#[Route('GET', '/hello', 'hello')]
public function index(): string { /* ... */ }
```

The constructor is `Route(string|array $method, string $url, string $name)`:

| Argument | Meaning |
|----------|---------|
| `$method` | HTTP verb(s): `'GET'`, `'post'` (case‑insensitive), an array `['GET','POST']`, or `'*'` for "all". |
| `$url` | The URL pattern. Matched as a regular expression anchored with `@^…$@D`. Plain paths match literally. |
| `$name` | A unique name for [reverse URL generation](#named-routes-and-geturl). |

Real examples from the bundled app:

```php
// application/welcome/controllers/MainController.php
#[Route('*', '/', 'home')]
public function index(): string { /* ... */ }
```

```php
// api/controllers/RestController.php
#[Route('get',    '/api/index',        'rest_index')]  public function index(): string { }
#[Route('get',    '/api/read/(\d+)',   'rest_read')]   public function read(string $id): string { }
#[Route('post',   '/api/create',       'rest_create')] public function create(): string { }
#[Route('put',    '/api/update/(\d+)', 'rest_update')] public function update(string $id): string { }
#[Route('delete', '/api/delete/(\d+)', 'rest_delete')] public function delete(string $id): string { }
```

### URL parameters become method arguments

The URL is a regex. Every **capture group** becomes a positional argument passed to the method,
in order, URL‑decoded:

```php
#[Route('get', '/api/read/(\d+)', 'rest_read')]
public function read(string $id): string   // $id receives the digits captured by (\d+)
```

Named groups work too — `/user/(?<id>.*)/view` — but only the captured value is passed
positionally; the name is for your readability. Because arguments arrive as strings, cast as
needed (`(int)$id`).

### The `*` wildcard method

`#[Route('*', '/', 'home')]` registers the route for **every** method in the router's "match all"
list. By default that list is:

```
GET, POST, PUT, DELETE, PATCH
```

(configurable via the `match all` key in `routes.php` config — see below).

## The `routes.php` config file

Attributes are convenient, but the router itself is driven by a plain array. Your
`config/…/routes.php` returns `['routes' => [...]]`, where each entry is:

```php
[
    'method'   => 'GET',                     // or ['GET','POST'] or '*'
    'url'      => '/blog/(\d+)',             // regex
    'callback' => [Blog::class, 'show'],     // [controller class, method]
    'name'     => 'blog.show',              // optional, for getUrl()
]
```

You rarely write these by hand for controllers — `RouterDetector` (below) generates them from
your `#[Route]` attributes. But the array form is what you use for **non‑routable named paths**:

```php
// entries with url + name but NO callback are not routable —
// they exist only so getUrl('assets') can resolve a path in one place
['url' => '/assets',     'name' => 'assets'],
['url' => '/assets/js',  'name' => 'javascript'],
['url' => '/assets/css', 'name' => 'css'],
['url' => '/images',     'name' => 'images'],
```

> **Never hardcode a routed path.** Give the path a name and resolve it with `getUrl('name')`.
> Then a path change happens in exactly one place.

## `RouterDetector`: attributes → routes in development

In development you don't maintain the routes array. `config/development/routes.php` calls
`RouterDetector::detect()`, which recursively reflects over your module directories, reads every
`#[Route]` attribute it finds, and returns the assembled routes array — plus any extra
non‑routable entries you pass:

```php
// config/development/routes.php
use config\development\RouterDetector;

require_once __DIR__ . '/RouterDetector.php';

return [
    'routes' => RouterDetector::detect([__ROOT__ . '/application', __ROOT__ . '/api'], [
        // extra non-routable named paths for getUrl()
        ['url' => '/assets',     'name' => 'assets'],
        ['url' => '/assets/js',  'name' => 'javascript'],
        ['url' => '/assets/css', 'name' => 'css'],
        ['url' => '/images',     'name' => 'images'],
    ]),
];
```

`RouterDetector::detect()` **refuses to run outside `ENVIRONMENT === 'development'`** — a
filesystem + reflection scan on every request is too expensive for production.

> **Adding a module.** When you add a new top‑level module (say `admin/`), add its path to the
> `detect([...])` array here *and* to the `export([...])` call used for production, so its
> `#[Route]` attributes are discovered.

## Production: pre‑generate the route table

For production you scan once, at deploy time, and save the result. `RouterDetector::export()`
returns the routes array as PHP source; write it to `config/production/routes.php`:

```php
// build once (e.g. in a deploy script), then save its output as config/production/routes.php
// the file must `return ['routes' => [...]];`
```

Because `ENVIRONMENT=production` adds `config/production/` to the config search path
automatically, that pre‑generated file is picked up with **no live scanning**. Regenerate it
whenever a module gains or loses a `#[Route]`.

## How matching works

`Router::match($uri, $method)` (called for you in the lifecycle) does this:

1. Normalises the URI to `'/' . trim($uri, '/')` and upper‑cases the method.
2. Iterates the routes registered for that method (last‑registered‑first).
3. For a **static** URL (no regex metacharacters) it compares by string equality — no regex
   engine. For a **pattern** URL it runs `preg_match("@^{$url}$@D", $normalizedUri)`.
4. On the first match it captures the arguments (URL‑decoded), records the match, and stops.
5. If nothing matches, it throws `RouteNotFound`.

The default config also ships a catch‑all `(.*)` route mapped to `FourohfourController` so an
unmatched request produces a 404 rather than a hard error.

## Named routes and `getUrl()`

Give a route a name and generate its URL later instead of hardcoding it. Use the global helper or
the router service:

```php
getUrl('hello');                       // '/hello'
getUrl('rest_read', [42]);             // '/api/read/42'
getUrl('assets');                      // '/assets'
container()->router->getUrl('home');   // '/'
```

`getUrl(string $name, array $arguments = [], ?bool $skipTypeChecking = null)`:

- Looks up the route by name (case‑insensitive); throws `RouterNameNotFound` if unknown.
- Substitutes `$arguments` into the URL's capture groups, **in order**.
- Requires the argument count to equal the number of capture groups, or throws `InvalidValue`.
- By default, **validates each argument against its capture group's regex** — `getUrl('rest_read',
  ['abc'])` throws because `abc` doesn't match `(\d+)`. Pass `true` as the third argument, or set
  `'skip parameter type checking' => true` in the routes config, to skip that check.

There is also `siteUrl(bool|string $prefix = true)` for the application's base URL, and the
`input`/`router` services expose the current request URI. See the
[Router reference](reference/router.md).

## Routing configuration

The router's behavior is tuned by the merged `routes` config (framework defaults in
`vendor/orange/framework/src/config/routes.php`):

| Key | Default | Purpose |
|-----|---------|---------|
| `routes` | `[]` | The route definitions (usually supplied by your `routes.php`). |
| `match all` | `['GET','POST','PUT','DELETE','PATCH']` | Which methods `'*'` expands to. |
| `skip parameter type checking` | `false` | Global default for `getUrl()` argument validation. |
| `404` | `FourohfourController::index` on `(.*)` | The catch‑all not‑found route. |
| `home` | `HomeController::index` on `/` | The default homepage route. |

## Summary

- Declare routes with `#[Route(method, url, name)]` on controller methods.
- Capture groups in the URL become positional, URL‑decoded method arguments.
- `RouterDetector::detect()` builds the table from attributes in development;
  `RouterDetector::export()` → `config/production/routes.php` for production.
- Name every route and resolve paths with `getUrl()` — never hardcode a URL.

---

[← The request lifecycle](03-request-lifecycle.md) · [Manual index](README.md) · [Next: Controllers →](05-controllers.md)
