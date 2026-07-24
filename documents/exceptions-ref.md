# Exception reference

[← Manual index](README.md)

Every exception the framework defines lives under `orange\framework\exceptions\…` and extends the
base **`OrangeException`**. They're grouped into sub‑namespaces (`http`, `router`, `container`, …)
so you can catch a whole category or a single type.

## How framework exceptions behave

`OrangeException extends \Exception` and adds two things:

- **A humanised message prefix.** The constructor splits the class name on capital letters and
  prepends it to your message — so throwing `RouteNotFound('[GET] /x')` produces the message
  `"Route Not Found [GET] /x"`. It also exposes `$className`, `$namespacedClass`, and `$classMsg`.
- **A `decorate(Error $error)` hook.** Subclasses can override it to interact with the central
  [`Error`](11-logging-and-errors.md#error-handling) responder while the error page is built.

Because every group has a base class (e.g. `exceptions\container\Container`,
`exceptions\filesystem\FileSystem`), you can catch broadly:

```php
use orange\framework\exceptions\filesystem\FileSystem;

try {
    // ...
} catch (FileSystem $e) {          // any filesystem exception
    logMsg('ERROR', $e->getMessage());
}
```

...or narrowly (`catch (FileNotFound $e)`), or catch **all** framework exceptions with
`catch (OrangeException $e)`.

---

## HTTP exceptions — `exceptions\http\`

These represent HTTP responses, not failures. The base `Http` class is special:

- If constructed with `$code = 0`, it **derives the status code from the last three digits of the
  class name** — `Http404` → `404`.
- If given no message, it fills one from `statusCodes.php` for that code.
- It exposes `getHttpCode(): int`.

Throw them directly, or use the [`show*()` helpers](13-helpers.md#http-errors--handlers-errorsphp):
the generic **`show($code, $msg)`** throws the `Http{code}` for any status (`show(404)` ≡
`show404()`), plus the named shortcuts (`show404()`, `redirect301()`, …). The
[`Error`](11-logging-and-errors.md) responder turns them into the matching status + error view.

### Success & redirect

| Class | Status | Helper |
|-------|--------|--------|
| `Http200` | 200 OK | — |
| `Http201` | 201 Created | — |
| `Http202` | 202 Accepted | — |
| `Http204` | 204 No Content | — |
| `Http301` | 301 Moved Permanently | `redirect301($url)` |
| `Http302` | 302 Found | — |
| `Http304` | 304 Not Modified | — |

### Client errors (4xx)

| Class | Status | Helper |
|-------|--------|--------|
| `Http400` | 400 Bad Request | `show400()` |
| `Http401` | 401 Unauthorized | `show401()` |
| `Http403` | 403 Forbidden | `show403()` |
| `Http404` | 404 Not Found | `show404()` |
| `Http405` | 405 Method Not Allowed | — |
| `Http406` | 406 Not Acceptable | — |
| `Http409` | 409 Conflict | — |
| `Http410` | 410 Gone | — |
| `Http418` | 418 I'm a teapot | — |
| `Http422` | 422 Unprocessable Entity | `show422()` |
| `Http423` | 423 Locked | — |
| `Http429` | 429 Too Many Requests | `show429()` |

### Server errors (5xx)

| Class | Status | Helper |
|-------|--------|--------|
| `Http500` | 500 Internal Server Error | `show500()` |
| `Http501` | 501 Not Implemented | — |
| `Http503` | 503 Service Unavailable | `show503()` |

Classes without a dedicated helper are thrown directly: `throw new Http405();`.

---

## General — `exceptions\`

Top‑level exceptions not tied to one service.

| Class | Signals |
|-------|---------|
| `OrangeException` | Base of every framework exception (catch‑all). |
| `InvalidValue` | A value failed validation — a bad route callback, a non‑string controller return, an alias chain too deep, a `getUrl()` argument that doesn't match its pattern. |
| `MissingRequired` | A required dependency is absent — e.g. the `mbstring` extension at bootstrap. |
| `NotFound` | A generic "not found" (e.g. an unknown service type during container debug). |
| `ResourceNotFound` | A searched‑for resource could not be located. |
| `IncorrectInterface` | An object doesn't implement the required interface — e.g. the `container` service didn't return a `ContainerInterface`. |
| `ClassLocked` | A mutation was attempted on an object locked against changes (e.g. a locked `DirectorySearch`). |
| `MagicMethodNotFound` | A magic `__get`/`__call` target does not exist. |

---

## Config — `exceptions\config\`

| Class | Signals |
|-------|---------|
| `Config` | Base for configuration errors. |
| `ConfigDirectoryNotFound` | A configured config directory does not exist. |
| `ConfigFileNotFound` | A required config file is missing. |
| `ConfigFileDidNotReturnAnArray` | A `config/*.php` file did not `return` an array. |
| `ConfigNotFound` | A requested config file/key was not found. |
| `InvalidConfigurationValue` | A config or `.env` value is invalid (e.g. malformed INI). |
| `ImmutableAccess` | An attempt to write to or unset the immutable `config` service. |

---

## Container — `exceptions\container\`

| Class | Signals |
|-------|---------|
| `Container` | Base for container errors. |
| `ServiceNotFound` | A requested service name is not registered. |
| `FailedToAutoWire` | An auto‑wire class has no public constructor or `getInstance()` to build from. |
| `AutoWire` | Base for auto‑wiring errors. |
| `CannotCloneSingleton` | A singleton service was `clone`d. |
| `CannotUnserializeSingleton` | A singleton service was unserialized. |
| `ConstructorNotPublic` | *(reserved)* The constructor to auto‑wire isn't public. |
| `UnableToResolve` | *(reserved)* A dependency could not be resolved. |

> `ConstructorNotPublic` and `UnableToResolve` are declared for completeness but are not currently
> thrown by the container.

---

## Dispatcher — `exceptions\dispatcher\`

| Class | Signals |
|-------|---------|
| `Dispatcher` | Base for dispatch errors. |
| `ControllerClassNotFound` | The matched route's controller class doesn't exist. |
| `ControllerFileNotFound` | The controller file could not be found. |
| `MethodNotFound` | The matched method doesn't exist or isn't public. |
| `ArgumentMissMatch` | The captured route arguments don't match the method's parameters. |

---

## Router — `exceptions\router\`

| Class | Signals |
|-------|---------|
| `Router` | Base for routing errors. |
| `RouteNotFound` | No route matched the request URI + method. |
| `RouterNameNotFound` | `getUrl()` was given an unknown route name (or the result was empty). |
| `HttpMethodNotSupported` | A route was registered with an unsupported HTTP method. |

---

## Filesystem — `exceptions\filesystem\`

| Class | Signals |
|-------|---------|
| `FileSystem` | Base for filesystem errors. |
| `File` | Base for file errors. |
| `FileNotFound` | A required file is missing — a helper, a controller library, an `.env` file. |
| `FileNotWritable` | A file can't be written. |
| `FileAlreadyExists` | A file exists where one shouldn't. |
| `Directory` | Base for directory errors. |
| `DirectoryNotFound` | A directory doesn't exist — e.g. `__ROOT__` or a config directory. |
| `DirectoryNotWritable` | A directory can't be written to — e.g. the log directory. |

---

## Input / Output / Network — `exceptions\input\`, `exceptions\output\`, `exceptions\network\`

| Class | Signals |
|-------|---------|
| `input\Input` | Base for input errors. |
| `input\ImmutableAccess` | An attempt to mutate the immutable `input` (request) snapshot. |
| `input\Request` | A request‑parsing error. |
| `input\UnknownOffset` | An unknown array offset was read on `input`. |
| `output\Output` | Base for output errors. |
| `output\Response` | A response error. |
| `network\Network` | Base for network errors. |

---

## View / Security / Fatal

| Class | Signals |
|-------|---------|
| `view\View` | Base for view errors. |
| `view\ViewNotFound` | A template could not be located on the view search path. |
| `security\Security` | A security‑toolkit error — key generation or a crypto operation failed. |
| `fatal\Fatal` | Base for fatal errors. |
| `fatal\DieException` | Wraps a `die()` so it can be caught/tested. |
| `fatal\ExitException` | Wraps an `exit()` so it can be caught/tested. |

---

## Catching by category

Because each group has a base class, a single `catch` handles a whole family:

```php
use orange\framework\exceptions\router\Router as RouterException;
use orange\framework\exceptions\OrangeException;

try {
    $container->router->match($uri, $method);
} catch (RouterException $e) {
    // RouteNotFound, RouterNameNotFound, HttpMethodNotSupported
    show404();
} catch (OrangeException $e) {
    // any other framework exception
    logMsg('ERROR', $e->getMessage());
    show500();
}
```

---

[← Manual index](README.md)
