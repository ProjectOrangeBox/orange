# Part II — API Reference

[← Manual index](../README.md)

Per‑service, method‑by‑method reference for the framework's public surface. Each page lists the
service's interface, constants, and methods with signatures, plus the exceptions they throw. For
conceptual explanation and worked examples, follow the "Guide" link on each page back to Part I.

> **Looking for config keys?** Every configurable class's options — key, default, type,
> description — are collected in the [Configuration reference](../config-ref.md).
>
> **Looking for exceptions?** Every framework exception type is catalogued in the
> [Exception reference](../exceptions-ref.md).

## Built-in services registry

Every service the container registers from `services.php`, with its alias, class, interface, and
config file. Access any of them via `container()->name`, the matching helper, or
`#[AttachService('name')]`. All are lazy (built on first use) unless noted.

| Service | Alias | Class | Interface | Config file |
|---------|-------|-------|-----------|-------------|
| `container` | — | `Container` | `ContainerInterface` | — (registers itself) |
| `config` | — | `Config` | `ConfigInterface` | *(application config)* |
| `log` | — | `Log` | `LogInterface` | `log.php` |
| `events` | `event` | `Event` | `EventInterface` | `event.php` |
| `input` | `request` | `Input` | `InputInterface` | `input.php` |
| `output` | `response` | `Output` | `OutputInterface` | `output.php` |
| `router` | — | `Router` | `RouterInterface` | `routes.php` |
| `data` | — | `Data` | `DataInterface` | *(none — empty)* |
| `view` | — | `View` | `ViewInterface` | `view.php` |
| `dispatcher` | — | `Dispatcher` | `DispatcherInterface` | — |
| `$mimes` | — | *(array value)* | — | `mimes.php` |
| `$application` | — | *(array value)* | — | *(merged `application.php`)* |

`$mimes` and `$application` are plain **value** services (not lazy) — an array, not a class. See
[Dependency injection](../08-dependency-injection.md) for the registration styles and
[Configuration reference](../config-ref.md) for each config file's keys.

## Runtime core

| Service | Class | Interface | Guide |
|---------|-------|-----------|-------|
| [Application](application.md) | `Application` | — | [Lifecycle](../03-request-lifecycle.md) |
| [Container](container.md) | `Container` | `ContainerInterface` | [DI](../08-dependency-injection.md) |
| [Router](router.md) | `Router` | `RouterInterface` | [Routing](../04-routing.md) |
| [Dispatcher](router.md#dispatcher) | `Dispatcher` | `DispatcherInterface` | [Lifecycle](../03-request-lifecycle.md) |

## HTTP

| Service | Class | Interface | Guide |
|---------|-------|-----------|-------|
| [Input](input.md) | `Input` | `InputInterface` | [Request & response](../09-request-and-response.md) |
| [Output](output.md) | `Output` | `OutputInterface` | [Request & response](../09-request-and-response.md) |

## Data, config & views

| Service | Class | Interface | Guide |
|---------|-------|-----------|-------|
| [Config](config.md) | `Config` | `ConfigInterface` | [Configuration](../07-configuration.md) |
| [Data](data.md) | `Data` | `DataInterface` | [Views](../06-views.md) |
| [View](view.md) | `View` | `ViewInterface` | [Views](../06-views.md) |

## Services

| Service | Class | Interface | Guide |
|---------|-------|-----------|-------|
| [Event](event.md) | `Event` | `EventInterface` | [Events](../10-events.md) |
| [Log](log.md) | `Log` | `LogInterface` | [Logging](../11-logging-and-errors.md) |
| [Security](security.md) | `Security` | `SecurityInterface` | [Security](../12-security.md) |

## Application building blocks

| Reference | Covers | Guide |
|-----------|--------|-------|
| [Controllers](controllers.md) | `BaseController`, `JsonController` | [Controllers](../05-controllers.md) |
| [Attributes](attributes.md) | `#[Route]`, `#[AttachService]`, `#[AutoWire]` | [Routing](../04-routing.md), [Controllers](../05-controllers.md) |
| [Utility helpers](utilities.md) | `Dot` (dot‑notation), `Ary` (arrays) | [Global helpers](../13-helpers.md) |

## Conventions

- Every service is a **singleton** unless stated otherwise: `container()->name` always returns the
  same instance. Services are constructed by the container from `services.php`, not with `new`.
- Signatures are copied from the framework interfaces and classes in
  `vendor/orange/framework/src/`. Fluent methods return `self` for chaining.
- Exception classes are under `orange\framework\exceptions\…`; all extend `OrangeException` so you
  can catch broadly or narrowly.
