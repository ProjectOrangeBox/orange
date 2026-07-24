# 7. Configuration

[← Views](06-views.md) · [Manual index](README.md) · [Next: Dependency injection →](08-dependency-injection.md)

Orange configuration is just PHP arrays. Each config file returns an array; the framework merges
same‑named files from several directories into one cascading result, so you can override defaults
per environment without editing them. Environment‑specific secrets live in a separate `.env`
file.

## Config files are PHP arrays

A config file is a `.php` file that `return`s an array:

```php
// config/application.php
<?php
declare(strict_types=1);

return [
    'h1'        => 'Hello World!',
    'position'  => 'Head Bottle Washer',
    'this file' => __FILE__,
];
```

## The `config` service

Read configuration through the `config` service (`ConfigInterface`), attached to every
`BaseController` as `$this->config`:

```php
// array access — $config['file']['key']
$h1 = $this->config['application']['h1'];

// dotted get() with a default
$h1 = $this->config->get('application.h1', 'Default');

// whole file as an array via property access
$app = $this->config->application;
```

Or use the global [`config()` helper](13-helpers.md) from anywhere:

```php
config('application', 'h1');          // the 'h1' key of application.php
config('application');                // the whole application.php array
config('application', 'missing', 'x'); // 'x' if the key is absent
```

The config service is **immutable** — it's read‑only, loaded‑once configuration. Array
writes/unsets throw.

## The config cascade

The magic is in how files are merged. When you load `view.php` (say), the framework looks for
`view.php` in each configured directory, **in order**, and merges them with
`array_replace_recursive` — so later directories override earlier ones.

By default the directories are, first to last:

1. **`vendor/orange/framework/src/config/`** — the framework's own defaults (always first).
2. **`config/`** — your application's config.
3. **`config/{ENVIRONMENT}/`** — environment‑specific overrides (e.g. `config/production/`).

So the effective value of any key is: framework default → overridden by your `config/` → overridden
by your `config/{ENVIRONMENT}/`. You never edit the framework's defaults; you shadow them.

```
src/config/output.php   'force https' => false      ┐
config/output.php        (absent)                    ├─►  merged: force https = true
config/production/output.php 'force https' => true   ┘        (in production only)
```

Because merging is recursive, you can override a single nested key and leave the rest of a config
file at its default.

> **The framework config is always first.** `Application::setConfigDirectories()` prepends
> `src/config` to whatever directories are used, so every framework service has sane defaults even
> if you provide no config file of your own.

## Environments

The active environment is the `ENVIRONMENT` constant, set from `.env`:

```ini
ENVIRONMENT = development
```

If `.env` doesn't set it, `ENVIRONMENT` **defaults to `production`** — a safe default. Its main
effects:

- It picks the `config/{ENVIRONMENT}/` override directory in the cascade.
- `RouterDetector::detect()` only scans for routes when `ENVIRONMENT === 'development'`; production
  uses the pre‑generated `config/production/routes.php`. See [Routing](04-routing.md).

## The `.env` file

`.env` is INI format, gitignored, and holds anything environment‑specific — the environment name,
debug flag, database credentials, API keys:

```ini
ENVIRONMENT = development
DEBUG = true

[db]
host     = localhost
port     = 3306
database = myapp
username = root
password = secret
charset  = utf8mb4
```

`Application` parses it with `parse_ini_file(..., INI_SCANNER_TYPED)` so `true`/numbers come back
as real booleans/ints, and INI `[sections]` become nested arrays. After loading, `$_ENV` is
**unset** so nothing else can read raw environment values.

Read values with the global `env()` helper — never from `$_ENV`:

```php
$debug = env('DEBUG', false);
$db    = env('db');                 // the whole [db] section as an array
$host  = env('db')['host'] ?? 'localhost';
```

`env('KEY', $default)` returns `$default` when the key is absent.

### `.env` vs config files

| Use `.env` for… | Use `config/*.php` for… |
|-----------------|--------------------------|
| Secrets and credentials | Application structure and defaults |
| Values that differ per machine/deployment | Values that are the same everywhere |
| Things you must not commit | Things you version‑control |

A common pattern is a config file that *reads* `.env`, keeping secrets out of committed code —
see the `pdo` service in [Dependency injection](08-dependency-injection.md), which builds its DSN
from `env('db')`.

## Constants

`config/constants.php` returns an array of name → value pairs that `Application` defines as PHP
constants during bootstrap. Names are **forced uppercase**, and existing constants are never
redefined:

```php
// config/constants.php
return [
    'APP_NAME' => 'My Application',
    'MAX_UPLOAD' => 5_000_000,
];
```

```php
echo APP_NAME;   // available everywhere after bootstrap
```

The framework already defines lifecycle constants this way: `UNDEFINED`, `RUN_MODE`, `CHARSET`,
`ENVIRONMENT`, `DEBUG`.

## `application.php` — runtime settings

`config/application.php` holds framework runtime settings applied during bootstrap. The keys the
framework reads include:

| Key | Effect |
|-----|--------|
| `display_errors`, `display_startup_errors` | `ini_set()` for PHP error display. |
| `error_reporting` | `error_reporting()` level. |
| `timezone` | `date_default_timezone_set()` (falls back to the system default). |
| `encoding` | `default_charset` + `mb_internal_encoding()`; also becomes `CHARSET`. |
| `umask` | Process umask, set to a known state. |
| `mb_substitute_character` | `mb_substitute_character()` for invalid‑byte handling. |
| `helpers`, `required helpers` | Helper files to `include_once` before the container is built. |

Anything you pass to `Application::http(array $config)` is `array_replace`d over the loaded
`application.php`, so tests and specialised entry points can override these at call time.

## Overriding the config directories

If you pass a directory list to `Application::make(null, [__ROOT__ . '/config'])`, that list
**replaces** the default `config/` + `config/{ENVIRONMENT}/` pair (the framework's `src/config`
is still prepended). This is why the standard `index.php` passes **no** config directories — doing
so would disable the per‑environment override folder. Only pass an explicit list when you
deliberately want a custom search path.

## Summary

- Config files return PHP arrays; the framework merges same‑named files across directories, later
  wins.
- Cascade order: framework `src/config` → your `config/` → `config/{ENVIRONMENT}/`.
- `ENVIRONMENT` (default `production`) selects the override folder; `.env` (INI) holds secrets,
  read via `env()`.
- Read config via `$this->config[...]`, `$this->config->get('file.key')`, or the `config()` helper.

---

[← Views](06-views.md) · [Manual index](README.md) · [Next: Dependency injection →](08-dependency-injection.md)
