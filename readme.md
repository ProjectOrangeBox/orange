# Orange Framework

A lightweight PHP 8.4+ MVC micro‑framework: a router, a dependency‑injection container,
request/response objects, a plain‑PHP view engine, cascading configuration, an event bus,
logging, and a small libsodium‑backed security toolkit — and very little else. No ORM, no
templating language, no compile step. You wire an application together with small, explicit
services and PHP attributes.

```php
// htdocs/index.php — the entire entry point
define('__ROOT__', realpath(__DIR__ . '/../'));
require_once __ROOT__ . '/vendor/autoload.php';

orange\framework\Application::make([__ROOT__ . '/.env'])->http();
```

```php
// a complete route: a controller method with a #[Route] attribute that returns a string
#[Route('GET', '/hello', 'hello')]
public function index(): string
{
    return '<h1>Hello, world!</h1>';
}
```

---

## 📖 Documentation

**The full manual lives in [`documents/`](documents/README.md).** Start there for guides and a
complete per‑service API reference.

### Guide

| Chapter | |
|---------|---|
| [Introduction](documents/01-introduction.md) | What Orange is, its philosophy, requirements |
| [Getting started](documents/02-getting-started.md) | Install, project layout, hello‑world, running locally |
| [The request lifecycle](documents/03-request-lifecycle.md) | `Application` and the fixed match → dispatch → send pipeline |
| [Routing](documents/04-routing.md) | `#[Route]` attributes, `routes.php`, `RouterDetector`, named routes |
| [Controllers](documents/05-controllers.md) | `BaseController`, `JsonController`, `#[AttachService]`, libraries |
| [Views](documents/06-views.md) | The plain‑PHP view engine, data, partials, search paths |
| [Configuration](documents/07-configuration.md) | The config cascade, environments, `.env`, constants |
| [Dependency injection](documents/08-dependency-injection.md) | The container, registration styles, auto‑wiring, singletons |
| [Request & response](documents/09-request-and-response.md) | The `input` and `output` services |
| [Events](documents/10-events.md) | The event bus and lifecycle hooks |
| [Logging & error handling](documents/11-logging-and-errors.md) | The PSR‑3 logger, error pages, HTTP exceptions |
| [Security](documents/12-security.md) | Encryption, signatures, password hashing, sanitisers |
| [Global helpers](documents/13-helpers.md) | The global functions loaded at bootstrap |

### API reference

Per‑service, method‑by‑method: see [`documents/reference/`](documents/reference/README.md) —
`Application`, `Container`, `Router`, `Input`, `Output`, `Config`, `Data`, `View`, `Event`, `Log`,
`Security`, controllers, and attributes.

---

## At a glance

The framework's public surface is a handful of container‑resolved services, each behind an
interface so it can be swapped:

| Service | Class | Purpose |
|---------|-------|---------|
| `container` | `Container` | Dependency‑injection container; builds and shares services lazily |
| `config` | `Config` | Cascading configuration merged from multiple directories |
| `router` | `Router` | Maps HTTP method + URL to a controller method; reverse URL generation |
| `dispatcher` | `Dispatcher` | Instantiates the matched controller and calls the method |
| `input` | `Input` | Immutable snapshot of the request (superglobals captured then unset) |
| `output` | `Output` | Buffered response — body, headers, status — sent at `send()` |
| `view` | `View` | Renders plain‑PHP templates; no templating language |
| `data` | `Data` | Shared `ArrayObject` data store passed into views |
| `events` | `Event` | Priority‑ordered publish/subscribe bus and lifecycle hooks |
| `log` | `Log` | PSR‑3 logger with a bitmask level threshold |
| `security` | `Security` | libsodium encryption, HMAC signatures, Argon2 passwords, sanitisers |

Bootstrapping and the request lifecycle are driven by `Application`, which loads the environment
and config, builds the container from `services.php`, and runs:

```text
before.router → router->match() → before.controller
  → dispatcher->call(controller) → before.output → output->send() → before.shutdown
```

Each `before.*` stage is an event you can subscribe to. See
[The request lifecycle](documents/03-request-lifecycle.md).

### Security responsibilities the framework does not take on

Some protections need per-application state or policy, so the kernel deliberately leaves them
to the layer above rather than shipping a half-opinion:

- **CSRF tokens.** Token generation, storage, and per-form verification depend on the session
  and on which routes you consider state-changing — so there is no `csrf` service. Build it as
  a `before.controller` listener (the `security` service provides HMAC signing via
  `createSignature()`/`verifySignature()` if you want stateless tokens). Two things the
  framework *does* do on your behalf: `Input::requestMethod()` only honors a `_method` /
  `X-HTTP-Method-Override` override on a real `POST`, and only to `PUT`/`PATCH`/`DELETE`, so a
  cross-site `GET` can never reach a destructive route; and `orange/cookies` defaults to
  `SameSite=Lax`, `HttpOnly`, and `Secure`.
- **Rate limiting and account lockout.** Shared cross-request state; see the same note in
  [orange/auth](https://github.com/ProjectOrangeBox/auth).
- **Authorization.** `orange/acl` answers "what may this user do"; the framework itself has no
  concept of a current user.

---

## Testing & tooling

Reports generated by the test suite (open in a browser after running the tests):

- [`unittest/results.html`](unittest/results.html) — PHPUnit pass/fail/error results.
- [`unittest/coverage/index.html`](unittest/coverage/index.html) — line/method/class coverage.

Common Composer scripts (see `composer.json` for the full list):

| Script | Does |
|--------|------|
| `composer test:orange` | Run the core unit tests |
| `composer test:orangeCoverage` | Core tests with code coverage |
| `composer type-check` | PHPStan static analysis |
| `composer lint` / `lint:fix` | phpcs (PSR‑12) / phpcbf auto‑fix |
| `composer rector` / `rector:fix` | Rector dead‑code/quality refactorings (dry‑run / apply) |

---

## Source layout

```text
src/
├── Application.php        # bootstrapper + request lifecycle
├── Container.php          # dependency-injection container
├── Router.php  Dispatcher.php
├── Input.php   Output.php
├── Config.php  Data.php   View.php
├── Event.php   Log.php    Security.php  Error.php
├── abstract/              # ViewAbstract (base view engine)
├── attributes/            # #[Route], #[AttachService], #[AutoWire]
├── base/                  # Singleton / Factory / ArrayObject building blocks
├── config/                # framework default configuration (merged first in the cascade)
├── controllers/           # BaseController, JsonController, Home/Fourohfour
├── exceptions/            # framework exception types (all extend OrangeException)
├── helpers/               # global functions + Ary/Dot/DirectorySearch utilities
├── interfaces/            # service contracts (swap any implementation)
├── property/              # RouterCallback value object
├── stubs/                 # no-op drop-in service replacements
└── views/                 # default error views
```

For a description of every class and its methods, see the
[API reference](documents/reference/README.md).
