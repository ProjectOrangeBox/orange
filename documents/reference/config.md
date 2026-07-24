# Reference: Config

[← Reference index](README.md) · Guide: [Configuration](../07-configuration.md)

`orange\framework\Config implements ConfigInterface` (which `extends ArrayAccess`) — the cascading
configuration manager. Scans the configured directories for `*.php` files, merges same‑named files
(later directories win), and lazily loads/caches each file on first access. Immutable. Reach it via
`container()->config`, the `config()` helper, or `$this->config` on a `BaseController`.

## Methods

### `get(string $filenameKey, mixed $defaultValue = null): mixed`

Read a value by dotted key (`'file.section.key'`) or a whole file (`'file'`), returning
`$defaultValue` if absent.

```php
$h1  = $this->config->get('application.h1', 'Default');
$app = $this->config->get('application');       // whole file array
```

### `__get(string $filename): mixed`

Property access returns a whole config **file** as an array.

```php
$view = $this->config->view;         // the merged view.php array
```

## Array access

`ConfigInterface extends ArrayAccess`. Read a file, then index into it:

```php
$h1 = $this->config['application']['h1'];
```

Writing or unsetting an offset **throws** — configuration is loaded once and immutable.

## The cascade

Same‑named files across the search directories are merged with `array_replace_recursive`, later
directories overriding earlier ones. Default order:

1. `vendor/orange/framework/src/config/` (framework defaults, always first)
2. `config/` (your app)
3. `config/{ENVIRONMENT}/` (environment overrides)

So `$config['output']['force https']` is the framework default unless your `config/output.php` or
`config/{ENVIRONMENT}/output.php` overrides that one key. See the [guide](../07-configuration.md).

## The `config()` helper

```php
config();                        // the Config service itself
config('application');           // whole application.php as an array
config('application', 'h1');     // one key
config('application', 'x', 'd'); // one key with a default
```

Unlike the service, the helper never throws if config isn't ready — it falls back to the default.

---

[← Reference index](README.md) · Guide: [Configuration](../07-configuration.md)
