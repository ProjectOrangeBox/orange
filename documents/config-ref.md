# Configuration reference

[← Manual index](README.md)

Every configurable framework class in one place. Each section is one class, with a table of its
config keys: **Key**, **Default**, **Type**, and **Description**. Defaults shown are the
framework's own defaults (from `vendor/orange/framework/src/config/`); you override any of them
through the [config cascade](07-configuration.md).

## How to override these

Config files are plain PHP arrays merged across directories, later wins:

```text
vendor/orange/framework/src/config/   (framework defaults — shown below)
  → config/                            (your overrides)
    → config/{ENVIRONMENT}/            (per-environment overrides)
```

To change a value, create the same‑named file in your `config/` (or `config/{ENVIRONMENT}/`) and
return only the keys you want to change — merging is recursive, so unspecified keys keep their
default:

```php
// config/output.php — override two keys, everything else stays default
return [
    'force https'   => true,
    'allowed hosts' => ['example.com', 'www.example.com'],
];
```

See [Configuration](07-configuration.md) for the full mechanism.

> **Types** are inferred from the default values — the config arrays themselves are untyped PHP.
> Octal defaults (file modes, umask) are written as PHP octal literals.

---

## Application — `application.php`

Runtime settings applied during bootstrap (error display, timezone, encoding, umask, helper
files). Guide: [Configuration](07-configuration.md#applicationphp--runtime-settings) ·
[Lifecycle](03-request-lifecycle.md).

| Key | Default | Type | Description |
|-----|---------|------|-------------|
| `display_errors` | `0` | int | `ini_set('display_errors', …)`. |
| `display_startup_errors` | `0` | int | `ini_set('display_startup_errors', …)`. |
| `error_reporting` | `0` | int | `error_reporting(…)` level (e.g. `-1` for all). |
| `config separator` | `'.'` | string | Separator for dotted config keys (`config->get('file.key')`). |
| `timezone` | `date_default_timezone_get()` | string | Passed to `date_default_timezone_set()`. |
| `encoding` | `'UTF-8'` | string | `default_charset` + `mb_internal_encoding()`; also becomes the `CHARSET` constant. |
| `umask` | `0000` | int (octal) | Process umask set to a known state. |
| `mb_substitute_character` | `'none'` | string\|int | `mb_substitute_character()` for invalid‑byte handling. |
| `required helpers` | `[wrappers.php, errors.php, helpers.php]` | array | Helper files always `include_once`'d before the container is built. |
| `helpers` | *(unset)* | array | Optional: additional helper files to include before `required helpers`. |

---

## Router — `routes.php`

The route table and matching behavior. Guide: [Routing](04-routing.md) · Reference:
[Router](reference/router.md).

| Key | Default | Type | Description |
|-----|---------|------|-------------|
| `routes` | `[]` | array | Route definitions `{method, url, callback, name}` (usually supplied by your `routes.php` / `RouterDetector`). |
| `match all` | `['GET','POST','PUT','DELETE','PATCH']` | array | HTTP methods that a `'*'` route expands to. |
| `skip parameter type checking` | `false` | bool | Global default for `getUrl()` argument‑vs‑regex validation. |
| `404` | `{method:'*', url:'(.*)', callback:[FourohfourController,'index'], name:'fourohfour'}` | array | Catch‑all not‑found route. |
| `home` | `{method:'*', url:'/', callback:[HomeController,'index'], name:'home'}` | array | Default homepage route. |

---

## Output — `output.php`

Response defaults, redirects, HTTPS enforcement, and CORS. Guide:
[Request & response](09-request-and-response.md#output-configuration) · Reference:
[Output](reference/output.md).

| Key | Default | Type | Description |
|-----|---------|------|-------------|
| `auto detect accepts type` | `true` | bool | Detect the response type from the request's `Accept` header. |
| `contentType` | `'text/html'` | string | Default response content type. |
| `charSet` | `'utf-8'` | string | Default response charset. |
| `language` | `'en'` | string | Default `Content-Language`. |
| `send length` | `false` | bool | Send a `Content-Length` header. |
| `default redirect code` | `301` | int | Status used by `redirect()` when unspecified. |
| `force http response code` | `301` | int | Status used by the `forceHttps()` redirect. |
| `force https` | `false` | bool | Redirect HTTP requests to HTTPS. |
| `allowed hosts` | `[]` | array | Host allowlist for HTTPS redirects — prevents Host‑header open redirects. Empty = never trust the request Host. |
| `enable cors` | `false` | bool | Emit CORS headers. |
| `allowed cors` | `[]` | array | Allowed CORS origins. |
| `access-control-allow-credentials` | `false` | bool | Send `Access-Control-Allow-Credentials: true` for allowed origins (cookie/HTTP‑auth APIs only). |
| `access-control-max-age` | `86400` | int | CORS preflight cache lifetime, seconds. |
| `access-control-allow-methods` | `'GET, POST, PUT, DELETE, OPTIONS'` | string | Allowed CORS methods. |
| `mimes` | *(see `mimes.php`)* | array | Extension → MIME‑type lookup table (~885 entries). |
| `status codes` | *(see `statusCodes.php`)* | array | HTTP status code → reason‑phrase lookup table (~94 entries). |

---

## View — `view.php`

Template search paths, extension, temp cache, and dynamic‑view toggles. Guide:
[Views](06-views.md#view-configuration) · Reference: [View](reference/view.md).

| Key | Default | Type | Description |
|-----|---------|------|-------------|
| `view paths` | `[]` | array | Extra directories to search (highest priority of the configured paths). |
| `default view paths` | `[framework src/views]` | array | Fallback directories, searched last. |
| `view aliases` | `[]` | array | Map a view name to another (e.g. a shared layout). |
| `temp directory` | `sys_get_temp_dir()` | string | Where `renderString()` writes its compiled cache. |
| `debug` | `DEBUG` | bool | Verbose view diagnostics (the `DEBUG` constant). |
| `extension` | `'.php'` | string | Template file extension. |
| `sub path size` | `6` | int | Internal bucketing size for the temp cache. |

---

## Log — `log.php`

Where and what the logger writes. Guide:
[Logging & error handling](11-logging-and-errors.md#log-configuration) · Reference:
[Log](reference/log.md).

| Key | Default | Type | Description |
|-----|---------|------|-------------|
| `threshold` | `Log::NONE` (`0`) | int (bitmask) | Which levels are written; OR the `Log::*` constants (`ERROR\|WARNING`, `Log::ALL`, …). Default writes nothing. |
| `filepath` | `__ROOT__/var/logs/{Y-m-d}.log` | string | Log file path. |
| `permissions` | `0644` | int (octal) | File mode applied to the log file. |
| `line format` | `'%timestamp %level %message %context' . PHP_EOL` | string | Line template; `%timestamp`, `%level`, `%message`, `%context` are substituted. |
| `timestamp format` | `'Y-m-d H:i:s'` | string | `date()` format for `%timestamp`. |

---

## Event — `event.php`

Primarily where you register lifecycle/custom listeners; it accepts one behavioral option. Guide:
[Events](10-events.md) · Reference: [Event](reference/event.md).

| Key | Default | Type | Description |
|-----|---------|------|-------------|
| `disabled` | `false` | bool | Start with the entire event bus disabled (no listeners run until `enable()`). |
| *(trigger name)* | — | array | Any other key is a **trigger name** mapping to a list of listeners: `['before.output' => [[$callable, $priority], …]]`. `$callable` is a `Closure` or `[Class::class, 'method']`; `$priority` is optional (default `PRIORITY_NORMAL`). |

---

## Security — `security.php`

File paths for the keys used by encryption and signatures. Guide: [Security](12-security.md) ·
Reference: [Security](reference/security.md).

| Key | Default | Type | Description |
|-----|---------|------|-------------|
| `public key` | `''` | string | Path to the X25519 public key file (encryption). |
| `private key` | `''` | string | Path to the X25519 private key file (decryption). |
| `auth key` | `''` | string | Path to the HMAC auth key file (signatures). |

Generate these with `createKeys()`; store them `0600`, outside version control.

---

## Error — `error.php`

Where the central error responder looks for error‑page templates. Guide:
[Logging & error handling](11-logging-and-errors.md#error-handling).

| Key | Default | Type | Description |
|-----|---------|------|-------------|
| `error view directory` | `'errors'` | string | Directory name (on the view search path) holding error templates, e.g. `errors/404.php`, `errors/dev/html/404.php`. |

---

## Controller — `controller.php`

Controller‑layer defaults. Guide: [Controllers](05-controllers.md).

| Key | Default | Type | Description |
|-----|---------|------|-------------|
| `autoload services` | `['input','output','config','data','view']` | array | Service names available to controllers by default. |

---

## Classes without configurable options

For completeness — these services take no tunable config keys:

| Class | Config file | Notes |
|-------|-------------|-------|
| **Input** | `input.php` | Returns `Input::fromGlobals()` — the captured request snapshot, not an options array. There are no tunable keys. Reference: [Input](reference/input.md). |
| **Data** | *(none)* | No `data.php` ships; the service is built with an empty config. Set values at runtime instead. Reference: [Data](reference/data.md). |
| **Constants** | `constants.php` | Not options — every key becomes a PHP constant (name forced uppercase). Ships empty; add your own. Guide: [Configuration](07-configuration.md#constants). |

---

## Lookup tables (data, not options)

Two framework config files are large fixed lookup tables consumed by `Output`, not tunable
settings. Override only if you need to add/replace entries.

| File | Consumed as | Contents |
|------|-------------|----------|
| `mimes.php` | Output config `mimes` | ~885 file‑extension → MIME‑type mappings. |
| `statusCodes.php` | Output config `status codes` | ~94 HTTP status code → reason‑phrase mappings. |

---

[← Manual index](README.md)
