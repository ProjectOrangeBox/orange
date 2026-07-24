# 3. The request lifecycle

[← Getting started](02-getting-started.md) · [Manual index](README.md) · [Next: Routing →](04-routing.md)

Everything Orange does for an HTTP request happens inside `Application::http()`. This chapter
traces that method step by step so you know exactly what runs, in what order, and where you can
hook in.

## The one entry point

`htdocs/index.php` defines `__ROOT__`, loads the autoloader, and calls:

```php
Application::make([__ROOT__ . '/.env'])->http();
```

- `Application::make(?array $environmentalFiles, ?array $configDirectories)` returns the
  singleton `Application`, loading the given `.env` file(s) and (optionally) overriding the config
  search path. It is safe to call more than once — the instance is created once and reused.
- `http()` bootstraps the container and then runs the request pipeline, returning the container.

There is a CLI counterpart, `run()`, which bootstraps the same container and hands it back
without touching routing or output — see [The container](08-dependency-injection.md) and
`Application::run()` in the [reference](reference/application.md).

## Bootstrap: building the world

Before any routing happens, `http()` calls `bootstrap('http', $config)`, which:

1. **Defines runtime constants** if not already set: `UNDEFINED` (a non‑null sentinel,
   `chr(0)`), `RUN_MODE` (`'http'` or `'cli'`), and later `CHARSET`.
2. **Verifies `__ROOT__`** is defined and is a real directory, then `chdir()`s into it.
3. **Loads the environment** (`loadEnvironment()`): copies `$_ENV` into a private array, unsets
   `$_ENV`, parses each `.env` file (INI, typed scanner), and defines the `ENVIRONMENT`
   (default `production`) and `DEBUG` (default `false`) constants.
4. **Resolves the config directories** (`setConfigDirectories()`): the framework's own
   `src/config` is always first, then your `config/`, then `config/{ENVIRONMENT}/` — unless you
   passed an explicit list to `make()`. See [Configuration](07-configuration.md).
5. **Loads and applies `application.php`**: sets `display_errors`, `error_reporting`, the
   timezone, the internal encoding (and `CHARSET`), and `umask`. Checks that `mbstring` is
   loaded.
6. **`preContainer()`** — includes helper files (yours plus the framework's required helpers),
   registers `errorHandler()`/`exceptionHandler()` if those functions now exist, and defines
   config‑declared constants (forced uppercase).
7. **`bootstrapContainer()`** — loads `services.php`, validates that a `container` service exists
   and is a closure, calls it, and stores the resulting `ContainerInterface`.
8. **`postContainer()`** — registers the merged application config in the container as the
   `$application` value service.

After bootstrap returns, the container is live and every service is resolvable.

> **Extending bootstrap.** `preContainer()` and `postContainer()` are `protected` hooks. Subclass
> `Application`, override one of them (call `parent::` first), and your `htdocs/index.php` can do
> `MyApplication::make(...)->http()`. You never redeclare the constructor.

## The HTTP pipeline

Once the container exists, `http()` runs this fixed sequence. Each `trigger()` call fires an
[event](10-events.md) you can subscribe to; the arguments in parentheses are what listeners
receive.

```php
// 1. before routing
$this->container->events->trigger('before.router', $input);

// 2. match the request to a route
$router->match($input->requestUri(), $input->requestMethod());

// 3. before dispatch
$this->container->events->trigger('before.controller', $router, $input);

// 4. dispatch the matched controller and capture its returned string
$output->write($dispatcher->call($router->getRouterCallback()));

// 5. before sending output
$this->container->events->trigger('before.output', $router, $input, $output);

// 6. send headers, status code, and body
$output->send();

// 7. before shutdown
$this->container->events->trigger('before.shutdown', $router, $input, $output);
```

Stage by stage:

| Stage | Service | What happens |
|-------|---------|--------------|
| `before.router` | — | Fires before matching. A listener sees the `input` service. |
| **match** | `router` | `Router::match()` finds the route whose method+URL regex matches the request, storing the result. Throws `RouteNotFound` if nothing matches. |
| `before.controller` | — | Fires after a route is chosen but before the controller runs. Sees `router` and `input`. |
| **dispatch** | `dispatcher` | `Dispatcher::call()` takes the matched `RouterCallback`, instantiates the controller, invokes the method with the captured URL arguments, and **requires the return value to be a string**. |
| **write** | `output` | The returned string is written to the output buffer. |
| `before.output` | — | Fires with the body buffered but not yet sent. Sees `router`, `input`, `output`. |
| **send** | `output` | `Output::send()` emits headers, the status code, and the body. |
| `before.shutdown` | — | The final hook, after output has been sent. |

### The events object

`$this->container->events` is the [Event](10-events.md) service (also aliased `event`). The four
`before.*` triggers are the framework's own lifecycle hooks; you register listeners for them in
`config/event.php`. For example, to run something on every request before the controller:

```php
// config/event.php
use orange\framework\interfaces\RouterInterface;
use orange\framework\interfaces\InputInterface;

return [
    'before.controller' => [
        [function (RouterInterface $router, InputInterface $input) {
            logMsg('INFO', 'Dispatching ' . $input->requestUri());
        }],
    ],
];
```

(The exact registration shape is covered in [Events](10-events.md).)

## What a controller must return

The single hard rule of the dispatch stage: **a controller method returns a string.** That string
becomes the response body. If a method returns anything else, `Dispatcher::call()` throws
`InvalidValue`. This is why:

- HTML controllers `return $this->view->render(...)` (which returns the rendered string), and
- JSON controllers `return $this->response(...)` (which returns the JSON‑encoded body).

Set status codes and headers on the `output` service *before* returning; the body is what you
return. See [Request & response](09-request-and-response.md).

## Errors during the lifecycle

If any stage throws, the registered `exceptionHandler()` takes over. It routes `Http*` exceptions
(like the one `show404()` throws) and — unless error reporting is fully silenced — every other
exception through `orange\framework\Error`, which finds the best matching error view and sends it.
[Chapter 11](11-logging-and-errors.md) covers error handling and the HTTP exception helpers.

## Summary

- One entry point (`index.php`) → `Application::make(...)->http()`.
- Bootstrap loads env, config, helpers, constants, and builds the container.
- The pipeline is **match → dispatch → send**, with a `before.*` event before each move.
- Controllers return strings; `output` sends them.

---

[← Getting started](02-getting-started.md) · [Manual index](README.md) · [Next: Routing →](04-routing.md)
