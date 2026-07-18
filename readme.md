### Testing

[unittest/results.html](unittest/results.html)
    Generated PHPUnit test results report—open it in a browser to see which tests passed/failed/errored and why after running the test suite.

[unittest/coverage/index.html](unittest/coverage/index.html)
    Generated PHPUnit code coverage report—open it in a browser to see line/method/class coverage per file after running the test suite.

### Orange Framework Runtime Core

`src/Application.php`
    Singleton bootstrapper and framework entry point. Loads `.env` file(s) and cascading config directories, applies runtime settings (timezone, encoding, error reporting, umask), builds the DI container from `config/services.php`, and drives the request lifecycle: `http()` runs routing → dispatch → output and fires the `before.router`, `before.controller`, `before.output`, and `before.shutdown` events; `run()` bootstraps the same container for CLI scripts.

`src/Container.php`
    Singleton dependency-injection container. Services register as plain values/objects, closures (lazily invoked with the container as their argument), aliases (`@name`), or autowired class names (`^name`, resolved via reflection and `#[AutoWire]` constructor attributes). Any resolved object extending `Singleton`/`SingletonArrayObject` is automatically cached so it's only built once.

`src/Router.php`
    Singleton route table keyed by HTTP verb. `match()` regex-matches a request URI + method against registered routes and stores the result; `getRouterCallback()` exposes it as a `RouterCallback` (controller, method, arguments) for the dispatcher. Supports named routes for reverse URL generation (`getUrl()`) with per-segment parameter validation, and can persist the compiled route table through an injected cache service.

`src/Dispatcher.php`
    Singleton that executes a matched route. Given a `RouterCallback`, it verifies the controller class and method exist and are public, instantiates the controller, calls the method with the route's captured arguments, and enforces that the return value is a string—throwing `ControllerClassNotFound`, `MethodNotFound`, `ArgumentMissMatch`, or `InvalidValue` when something doesn't line up.

`src/Input.php`
    Singleton wrapper around the superglobals (`$_GET`/`$_POST`/`$_SERVER`/`$_COOKIE`/`$_FILES`) and the raw input stream, captured once via `Input::fromGlobals()` and then unset from PHP for safety. Parses JSON/urlencoded bodies, normalizes server/header keys, and exposes `query()`, `request()`, `cookie()`, `file()`, `server()`, `header()`, `requestUri()`, `uriSegment()`, `requestMethod()` (honors `_method` overrides), and `requestType()`/`isAjaxRequest()`/`isCliRequest()`/`isHttpsRequest()`.

`src/Output.php`
    Singleton response buffer—body, headers, status code, content type, and charset—flushed to the client by `send()`. Handles `redirect()` and an optional `forceHttps()` (validated against a configured "allowed hosts" allowlist to prevent open-redirects via a spoofed `Host` header), and wraps PHP's `header()`/`echo`/`exit` in overridable protected methods so behavior is testable without real output.

### Support Services

`src/Config.php`
    Singleton config manager. Scans the configured directories for `*.php` files, merges same-named files across directories (later directories win, so environment-specific files can override defaults), and lazily loads/caches each file on first access. Supports property access (`$config->file`), array access (`$config['file']`), and dotted-key lookups (`$config->get('file.key', $default)`).

`src/Event.php`
    Singleton publish/subscribe event bus used for framework lifecycle hooks (`before.router`, `before.controller`, etc.) and custom triggers. Listeners—closures or `[Class::class, 'method']` pairs—register against a named trigger with a priority and run highest-priority-first when `trigger()` fires; a listener can halt the chain by returning `false`. Supports a global `disable()`/`enable()` switch.

`src/Error.php`
    Singleton central error responder, normally instantiated from a registered exception/error handler. Pulls message/code/trace off a `Throwable`, searches environment- and request-type-specific view directories (e.g. `errors/dev/html/404.php`, falling back to `errors/404.php`) for a matching template, and if none is found renders an HTML-escaped raw fallback (HTML `<pre>`, JSON, or plain text depending on request type)—then sends it through `Output` and exits.

`src/abstract/ViewAbstract.php`
`src/View.php`
    `ViewAbstract` is the base class every view engine extends (`View` is the concrete PHP-template implementation). Locates templates via an internal `DirectorySearch`, supports view name aliases and dynamic `$c`/`$m`/`$1`/`$2` placeholders resolved from the matched route (controller/method/namespace segments), renders a file (`render()`) or an ad-hoc string compiled to a cached temp file (`renderString()`) with injected data, and lets callers toggle `debug`, `allowDynamicViews`, or `tempDirectory` at runtime via `change()`.

`src/Data.php`
    Singleton `ArrayObject`-based shared data store (property- and array-style access) used to pass data into views and share state between services/controllers without a global variable.

`src/interfaces`
    Declares contracts for core services (container, router, input, output, etc.) to keep implementations swappable and test-friendly.

### Ops & Infrastructure

`src/Security.php`
    Singleton libsodium-backed security toolkit. `createKeys()` generates an X25519 keypair plus an HMAC auth key (written `chmod 0600`, optionally owner-restricted); `encrypt()`/`decrypt()` use `crypto_box_seal`; `createSignature()`/`verifySignature()` use HMAC; `encodePassword()`/`verifyPassword()` use Argon2 (`sodium_crypto_pwhash_str`); `removeInvisibleCharacters()`/`cleanFilename()` sanitize untrusted strings. Sensitive buffers are zeroed (`sodium_memzero`) after use.

`src/Log.php`
    Singleton PSR-3 compliant logger (`Psr\Log\LoggerInterface`). Honors a configurable bitmask threshold to decide which levels are active, and either forwards messages to an injected PSR-3 handler or writes them itself to a configured file path (creating the directory and applying file permissions as needed).

`src/controllers/BaseController.php`
    Optional base class for application controllers. Auto-attaches services declared with `#[AttachService('name')]` property attributes, `include`s any local `libraries/*.php` files listed in `$libraries`, registers the controller's sibling `views/` directory with the view engine's search path, and calls an optional `beforeMethodCalled()` hook after setup.

`src/controllers/HomeController.php`
    Default landing controller; swap it to customize the "/" route quickly.

`src/controllers/FourohfourController.php`
    Default 404 handler wired up in `src/config/routes.php`; just calls the `show404()` helper.

`src/attributes/*`
    PHP attributes used for declarative wiring instead of config arrays: `#[AttachService('name')]` marks a `BaseController` property to be pulled from the container (see `BaseController::autoAttachService()`); `#[AutoWire('name')]` is stacked once per positional argument on a constructor or `getInstance()` method (in declaration order) to resolve each argument from the container when the class is auto-wired (see `Container::autoWire()`); `#[Route(method, url, name)]` marks a controller method as a route definition for the router to discover.

`src/base/*`
    Shared building blocks behind the framework's OOP singletons/factories: `Factory` (+ `FactoryTraits`) creates a fresh instance on every `getInstance()` call; `Singleton` (+ `SingletonTraits`) caches one instance per subclass instead; `BaseTraits` supplies the shared non-public constructor, `newInstance()`, and clone/wakeup guards common to both. `ArrayObject` extends PHP's `ArrayObject` with property-style access, an `array_*`-function passthrough via `__call()`, and a `merge()` helper; `SingletonArrayObject` combines it with the singleton behavior (used by `Data`).

`src/property/RouterCallback.php`
    Plain value object (controller, method, arguments) produced by `Router::getRouterCallback()` and consumed by `Dispatcher`.

`src/traits/ConfigurationTrait.php`
    Shared behavior for services configured from `src/config/*.php` files: `getConfigFile()`/`mergeConfigWith()` load and merge a config file by convention (or explicit path), `setFromConfig()`/`assignFromConfig()` push config values into setter methods or matching properties, `changeOption()` lets callers safely mutate a single option with type checking (`$changeableTypeCheck`), and `validateConfig()` checks present config values against simple rule strings (a type like `string`/`array`, or `min[n]`/`max[n]`/`count[n]`/`size[n]`/`class[X]`, etc).

`src/stubs/*`
    No-op drop-in replacements for services (e.g. `Log`, `Output`) you can register in the container instead of the real implementation, so code that depends on a service doesn't blow up when that service is intentionally disabled. See `src/stubs/README`.

`src/helpers/*`
    Standalone utility classes and global functions loaded by `Application`:
    - `Ary.php` — static array helpers (remap keys/values, etc.).
    - `Dot.php` — get/set/delete on arrays or `stdClass` using dot-notation keys.
    - `DirectorySearch.php` — configurable file-resource search/cache utility (recursive directory scanning, first/last precedence) used by the view engine and `Error` to locate templates.
    - `errors.php` — `show400()`/`show404()`/etc. global functions that throw the matching framework HTTP exception.
    - `helpers.php` — general-purpose globals: `is_closure()`, `file_put_contents_atomic()`, HTML/string builders, escaping helpers.
    - `wrappers.php` — `container()` and `logMsg()` globals for easy access to the DI container and logger from anywhere (including outside a class).

`src/config`
    Default configuration settings

`src/exceptions/*`
    framework-specific exception types (HTTP, router, container, filesystem, etc.) These extend OrangeException to make it easier to capture specific exceptions

`src/interfaces/*`
    framework-specific interfaces which should be extended to make replacing orange framework classses with your own.
