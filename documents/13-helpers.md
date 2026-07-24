# 13. Global helpers

[← Security](12-security.md) · [Manual index](README.md)

Orange loads a set of global functions at bootstrap so common tasks — reaching a service, logging,
escaping, throwing an HTTP error — read as one‑liners from anywhere, including outside a class.

## How they're loaded

These functions are **not** autoloaded by Composer. `Application::preContainer()` `include_once`s
the framework's helper files (`helpers.php`, `wrappers.php`, `errors.php`) plus any helper files
you list in `config/application.php`'s `helpers` key. Every function is guarded with
`if (!function_exists(...))`, so you can override any of them by defining your own version first
(e.g. in a helper file loaded earlier).

> **Tooling note.** Because these are runtime includes, static analysers and tests must bootstrap
> the helper files explicitly — the project already wires this into `phpstan.neon` and
> `unittest/bootstrap.php`.

## Service accessors (`wrappers.php`)

Shortcuts to the container and its most‑used services:

| Function | Returns | Notes |
|----------|---------|-------|
| `container()` | `ContainerInterface` | The one container instance. |
| `config(?$file, ?$key, $default)` | mixed | Read config. `config('app')` = whole file; `config('app','k')` = one key; no args = the config service. |
| `input()` | `InputInterface` | The request service. |
| `output()` | `OutputInterface` | The response service. |
| `getUrl($name, $args, $skipTypeCheck)` | `string` | Reverse‑generate a route URL (see [Routing](04-routing.md)). |
| `env($key, $default)` | mixed | Read a value from the loaded `.env`. |

```php
$router = container()->router;
$title  = config('application', 'h1');
$path   = getUrl('rest_read', [42]);       // '/api/read/42'
$dbHost = env('db')['host'] ?? 'localhost';
```

## Logging (`wrappers.php`)

| Function | Purpose |
|----------|---------|
| `logMsg($level, $message, $context = [])` | Write a log message; no‑ops if logging isn't ready. |
| `isLogEnabled($level)` | Whether that level would be written — guard costly messages with it. |

See [Logging & error handling](11-logging-and-errors.md).

## HTTP errors & handlers (`errors.php`)

Each `show*()` throws the matching framework HTTP exception, which the error handler turns into a
response. `show($code, $msg)` is the **generic form** — it throws the exception for any status
code, so `show(404)` equals `show404()`:

| Function | Result |
|----------|--------|
| `show($code, $msg='')` | Throws the `Http{code}` exception for **any** status code. `show(404)` ≡ `show404()`; for 3xx codes `$msg` is the redirect (Location) URL. Codes with no dedicated class fall back to the base `Http` (unknown → 500). |
| `show400($msg='')` | 400 Bad Request |
| `show401($msg='')` | 401 Unauthorized |
| `show403($msg='')` | 403 Forbidden |
| `show404($msg='')` | 404 Not Found |
| `show422($msg='')` | 422 Unprocessable Entity |
| `show429($msg='')` | 429 Too Many Requests |
| `show500($msg='')` | 500 Internal Server Error |
| `show503($msg='')` | 503 Service Unavailable |
| `redirect301($url, $msg='')` | 301 redirect (thrown) |

Also defined here (and registered by `Application`): `errorHandler()` (converts PHP errors to
`ErrorException`) and `exceptionHandler()` (routes uncaught exceptions through `Error`). Override
either by defining your own before the framework's is loaded. See
[Logging & error handling](11-logging-and-errors.md).

## String & HTML helpers (`helpers.php`)

### Escaping

| Function | Purpose |
|----------|---------|
| `e($input, $flags, $encoding, $doubleEncode)` | HTML‑escape a **string or array** (recursive `htmlspecialchars`, `ENT_QUOTES\|ENT_SUBSTITUTE\|ENT_HTML401`). **Use this in views for XSS safety.** |
| `esc($string)` | Escape double quotes only (`"` → `\"`) — for embedding inside a double‑quoted attribute/JS string, *not* general HTML escaping. |

```php
<h1><?= e($title) ?></h1>                     <!-- HTML escaping -->
<input value="<?= esc($value) ?>">            <!-- quote escaping -->
```

### Building & inspecting strings

| Function | Purpose |
|----------|---------|
| `element($tag, $attr = [], $content = '', $escape = true)` | Build an HTML element string. |
| `dataUri($file)` | Encode a file as a `data:` URI. |
| `convertLabel($value, $case = 'camel')` | Convert a label between cases (camel, etc.). |
| `concat(...)` | Concatenate all arguments (the "missing" concat function). |
| `strContains($haystack, $needle)` | `str_contains()` polyfill (empty needle → true). |
| `nthfield($string, $separator, $nth)` | The Nth field of a delimited string. |
| `after($tag, $string)` / `before($tag, $string)` | Substring after / before a marker. |
| `between($start, $end, $string)` | Substring between two markers. |
| `left($string, $n)` / `right($string, $n)` / `mid($string, $start, $len)` | Substring by position. |
| `isAssociative($array)` | Whether an array is associative. |

### Files

| Function | Purpose |
|----------|---------|
| `is_closure($c)` | Whether a value is a `Closure`. |
| `file_put_contents_atomic($path, $content, $flags = 0, $context = null)` | Write a file atomically (temp + rename). |
| `sanitizeDownloadFilename($filename)` | Make a filename safe for a `Content‑Disposition` header. |
| `forceDownload($filename = '', $dataOrPath = '', $contentType = null)` | Stream a download to the client and exit (`never` returns). |

```php
// atomic write avoids a half-written file if the process dies mid-write
file_put_contents_atomic(__ROOT__ . '/var/cache/data.json', json_encode($data));

// offer a generated file as a download
forceDownload('report.csv', $csvString, 'text/csv');
```

## Overriding a helper

Since every function is guarded, define your own first to win:

```php
// a helper file listed in config/application.php's 'helpers', loaded before the framework's
if (!function_exists('logMsg')) {
    function logMsg(mixed $level, string $msg, array $context = []): void
    {
        // your implementation
    }
}
```

## Summary

- Helpers are runtime `include_once`s, guarded by `function_exists()`, overridable.
- Service shortcuts: `container()`, `config()`, `input()`, `output()`, `getUrl()`, `env()`.
- Logging: `logMsg()`, `isLogEnabled()`.
- HTTP errors: `show($code, $msg)` (generic), the `show400()`…`show503()` shortcuts, `redirect301()`.
- HTML/strings/files: `e()` (HTML‑escape), `esc()`, `element()`, `forceDownload()`,
  `file_put_contents_atomic()`, and the substring family.

---

[← Security](12-security.md) · [Manual index](README.md)
