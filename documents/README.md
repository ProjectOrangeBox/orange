# Orange Framework — Manual

Orange is a lightweight PHP 8.4+ MVC micro‑framework: a router, a dependency‑injection
container, request/response objects, a plain‑PHP view engine, cascading configuration, an
event bus, logging, and a small security toolkit — and very little else. There is no ORM, no
templating language, and no compile step. You wire your application together with small,
explicit services and PHP attributes.

This manual is split into two parts:

- **Part I — Guide** teaches the framework task by task: install it, understand the request
  lifecycle, add routes and controllers, render views, configure services, and so on.
- **Part II — Reference** documents each service class method by method.

Read the guide front to back the first time; keep the reference open while you build.

---

## Part I — Guide

| # | Chapter | What it covers |
|---|---------|----------------|
| 1 | [Introduction](01-introduction.md) | What Orange is, its philosophy, and requirements |
| 2 | [Getting started](02-getting-started.md) | Install, project layout, "hello world", running locally |
| 3 | [The request lifecycle](03-request-lifecycle.md) | `index.php` → `Application` → the fixed pipeline and its events |
| 4 | [Routing](04-routing.md) | `#[Route]` attributes, `routes.php`, named routes, `RouterDetector`, production |
| 5 | [Controllers](05-controllers.md) | `BaseController`, `JsonController`, `#[AttachService]`, libraries |
| 6 | [Views](06-views.md) | The plain‑PHP view engine, `render()`, data, partials, search paths |
| 7 | [Configuration](07-configuration.md) | The config cascade, environments, `.env`, constants |
| 8 | [Dependency injection](08-dependency-injection.md) | The container, registration syntaxes, auto‑wiring, singletons |
| 9 | [Request & response](09-request-and-response.md) | The `input` and `output` services |
| 10 | [Events](10-events.md) | The event bus and lifecycle hooks |
| 11 | [Logging & error handling](11-logging-and-errors.md) | The PSR‑3 logger, error pages, HTTP exceptions |
| 12 | [Security](12-security.md) | libsodium encryption, signatures, password hashing, sanitizers |
| 13 | [Global helpers](13-helpers.md) | The global functions loaded at bootstrap |

## Quick reference

Cross‑cutting lookup tables that span the whole framework:

- **[Configuration reference](config-ref.md)** — every configurable class's options (key, default,
  type, description), plus a services registry and constants.
- **[Exception reference](exceptions-ref.md)** — every framework exception type, grouped, with an
  HTTP‑exception ↔ status ↔ `show*()` table.
- **[Utility helpers](reference/utilities.md)** — the `Dot` (dot‑notation) and `Ary` (array) static
  helper classes.

## Part II — Reference

See [reference/README.md](reference/README.md) for the full per‑service API reference
(`Application`, `Container`, `Router`, `Input`, `Output`, `Config`, `Data`, `View`, `Event`,
`Log`, `Security`, controllers, and attributes).

---

## Conventions used in this manual

- `orange\framework\…` is the framework's PSR‑4 namespace, mapped to `vendor/orange/framework/src/`.
- Code blocks are real, runnable PHP unless noted. Examples are drawn from the framework's own
  source and the bundled example application (`application/welcome`, `api/`).
- "The container" always means the one `orange\framework\Container` instance built during
  bootstrap and reachable everywhere via the `container()` helper.
- File paths are relative to the application root (`__ROOT__`) unless they start with
  `vendor/orange/framework/`, in which case they are inside the framework package itself.

## A note on where code lives

`vendor/orange/framework` is a Composer dependency, but it is the framework author's own code,
developed in place as a git clone. Your **application** code lives outside it — in
`application/`, `api/`, `config/`, and `htdocs/` at the project root. This manual documents the
framework; the example app is only used for illustration.
