# Reference: Application

[← Reference index](README.md) · Guide: [The request lifecycle](../03-request-lifecycle.md)

`orange\framework\Application` — singleton bootstrapper and entry point. It loads the environment
and configuration, builds the DI container, and drives the HTTP or CLI lifecycle. It is
constructed only through the static `make()`/`get()` methods (the constructor is `protected`).

## Static methods

### `make(?array $environmentalFiles = null, ?array $configDirectories = null): Application`

Return the singleton `Application`, creating it on first call. `$environmentalFiles` is a list of
`.env` files to load (INI, typed scanner). `$configDirectories`, if given, **replaces** the
default `config/` + `config/{ENVIRONMENT}/` search pair (the framework's own `src/config` is still
prepended). Safe to call repeatedly.

- **Throws** `FileNotFound` if an env file doesn't exist; `InvalidConfigurationValue` if it isn't
  valid INI.

```php
Application::make([__ROOT__ . '/.env'])->http();
```

### `get(): Application`

Alias for `make()` with no arguments — reads as "get the instance" rather than "make" one. Used by
the `env()` helper.

## Instance methods

### `http(array $config = []): ContainerInterface`

Bootstrap the container and run the full HTTP pipeline: `before.router` → `match` →
`before.controller` → dispatch → write → `before.output` → `send` → `before.shutdown`. Returns the
container. `$config` is `array_replace`d over the loaded `application.php`.

- **Throws** `InvalidValue`, `DirectoryNotFound`, `ConfigFileDidNotReturnAnArray`,
  `MissingRequired`, `FileNotFound`, `IncorrectInterface`.

### `run(array $config = []): ContainerInterface`

Bootstrap the container for **CLI** use and return it — no routing, dispatch, or output. Use it to
build the container in scripts and then resolve services yourself. Same `$config` semantics and
throws as `http()`.

```php
$container = Application::make([__ROOT__ . '/.env'])->run();
$db = $container->pdo;
```

### `env(string $key, mixed $default = null): mixed`

Read a value from the loaded environment. `$_ENV` is unset after loading, so this (and the global
`env()` helper, which delegates here) is the only way to read env values.

### `loadEnvironment(): void`

Load system env + any `.env` files passed to `make()`, unset `$_ENV`, and define the `ENVIRONMENT`
(default `production`) and `DEBUG` (default `false`) constants. Idempotent — runs once.

### `setConfigDirectories(): void`

Establish the config search path: framework `src/config` first, then either the directories passed
to `make()` or the default `config/` + `config/{ENVIRONMENT}/`. Idempotent.

## Protected extension hooks

Subclass `Application` and override these to customise bootstrap without touching the core. Never
redeclare the constructor.

| Hook | Runs | Typical use |
|------|------|-------------|
| `preContainer(): void` | Before the container is built | Load extra helpers, register handlers, define constants |
| `postContainer(): void` | After the container is built | Register additional services/values (the base sets `$application`) |
| `bootstrap(string $mode, array $config)` | The whole bootstrap sequence | Rarely overridden directly |

```php
final class MyApplication extends orange\framework\Application
{
    protected function postContainer(): void
    {
        parent::postContainer();
        $this->container->set('clock', fn() => new App\Clock());
    }
}

// htdocs/index.php
MyApplication::make([__ROOT__ . '/.env'])->http();
```

## Framework constants

Constants available application‑wide. `__ROOT__` and `__WWW__` are defined by your `index.php`
before the framework loads; the rest are defined by `Application` during bootstrap. Each is only
defined if not already set, so you can pre‑define any of them.

| Constant | Value | Set by |
|----------|-------|--------|
| `__ROOT__` | Project root directory (absolute) | `index.php` (required) |
| `__WWW__` | Public web root (`__ROOT__/htdocs`) | `index.php` |
| `UNDEFINED` | `chr(0)` — a non‑null "no value" sentinel | bootstrap |
| `RUN_MODE` | `'http'` or `'cli'` | bootstrap (`http()` / `run()`) |
| `ENVIRONMENT` | `.env` `ENVIRONMENT`, default `'production'` | `loadEnvironment()` |
| `DEBUG` | `.env` `DEBUG`, default `false` | `loadEnvironment()` |
| `CHARSET` | the configured `encoding` (default `UTF-8`) | bootstrap |

---

[← Reference index](README.md) · Guide: [The request lifecycle](../03-request-lifecycle.md)
