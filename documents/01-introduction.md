# 1. Introduction

[← Manual index](README.md) · [Next: Getting started →](02-getting-started.md)

## What Orange is

Orange is a small MVC framework for PHP. It gives you the pieces every web application needs and
stops there:

- a **router** that maps HTTP method + URL to a controller method;
- a **dependency‑injection container** that builds and shares services on demand;
- **request** (`input`) and **response** (`output`) objects that wrap the superglobals and the
  output buffer;
- a **view engine** that renders plain PHP templates — no templating language;
- **cascading configuration** loaded from plain PHP arrays, overridable per environment;
- an **event bus** for hooking into the request lifecycle;
- a **PSR‑3 logger**, a central **error responder**, and a libsodium‑backed **security** toolkit.

Everything is exposed as a named service in the container and, where it makes sense, behind an
interface so you can swap an implementation for your own.

## Design philosophy

**Explicit over magic.** Routes are declared with an attribute on the method that handles them.
Services are registered in a config file you can read. There is no hidden convention scanning at
runtime in production — you generate a route list once and ship it.

**Lazy by default.** Services are registered as closures and only built the first time they are
requested. An app that never touches the database never opens a connection; an app that never
logs never creates a log file.

**No template language.** Views are `.php` files. Data is `extract()`ed into scope and the file
is `require`d. Partials are `include`. If you know PHP, you already know the view layer.

**Interfaces at the seams.** Core services (`RouterInterface`, `OutputInterface`,
`ConfigInterface`, …) are defined as interfaces. The container is typed against them, so you can
register a replacement that honours the same contract and nothing else has to change.

**Testable I/O.** The `Input` service captures the superglobals once and then unsets them; the
`Output` service buffers everything and only touches PHP's real `header()`/`echo` at `send()`
time, behind overridable protected methods. Both make the request/response cycle unit‑testable.

## Requirements

| Requirement | Notes |
|-------------|-------|
| **PHP 8.4+** | The framework's `composer.json` requires `php >=8.4`. It uses constructor property promotion, first‑class callable syntax, `readonly`/typed properties, enums‑style class constants, and attributes throughout. |
| **ext‑mbstring** | Required and checked at bootstrap; bootstrap throws `MissingRequired` if it is missing. |
| **ext‑sodium** | Only needed if you use the `Security` service. |
| **Composer** | The framework is installed and autoloaded as a Composer package (`orange/framework`). |

## How the pieces fit together

```
                         ┌─────────────────────────────┐
   HTTP request  ───────▶│  htdocs/index.php           │
                         │  Application::make(...)      │
                         │        ->http()             │
                         └──────────────┬──────────────┘
                                        │ builds
                                        ▼
                         ┌─────────────────────────────┐
                         │  Container (DI)             │
                         │  config, input, output,     │
                         │  router, view, data, log... │
                         └──────────────┬──────────────┘
                                        │ drives the pipeline
                                        ▼
      before.router → router->match() → before.controller
        → dispatcher->call(controller) → before.output
          → output->send() → before.shutdown
```

Each stage is a service call, and each arrow labelled `before.*` is an event you can subscribe
to. The whole of [Chapter 3](03-request-lifecycle.md) walks through this pipeline.

## What this manual does not cover

- **Database access.** Orange ships no ORM or query builder. The example app registers a plain
  `PDO` service in `config/services.php` and uses it directly. Persistence is your choice.
- **The example application in depth.** `application/welcome` and `api/` are referenced for
  examples but are your code, not the framework.
- **The framework's internal test suite and tooling.** See the project `CLAUDE.md` and
  `composer.json` scripts for `test:orange`, `type-check`, `lint`, etc.

---

[← Manual index](README.md) · [Next: Getting started →](02-getting-started.md)
