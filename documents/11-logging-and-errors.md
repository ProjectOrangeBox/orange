# 11. Logging & error handling

[← Events](10-events.md) · [Manual index](README.md) · [Next: Security →](12-security.md)

Orange ships a PSR‑3 logger and a central error responder. This chapter covers writing log
messages efficiently, and how thrown exceptions become error pages — including the `show4xx()`
family of HTTP exception helpers.

## Logging

### The `log` service and `logMsg()`

The logger is `orange\framework\Log` (implementing `LogInterface` and PSR‑3's
`LoggerInterface`). The easiest way to log from anywhere — inside a class or not — is the global
`logMsg()` helper:

```php
logMsg('INFO', 'User logged in', ['id' => $user->id]);
logMsg('DEBUG', __METHOD__, ['args' => $args]);
logMsg('ERROR', 'Payment failed: ' . $e->getMessage());
```

`logMsg(mixed $level, string $message, array $context = [])` forwards to the `log` service. If the
container or log service isn't set up yet (very early bootstrap), it silently does nothing rather
than crash.

You can also use the service directly for PSR‑3 style calls:

```php
$log = container()->log;
$log->write(LogInterface::WARNING, 'Disk almost full', ['pct' => 92]);
```

### Log levels and the threshold

Levels are a **bitmask**. The logger only writes a message whose level bit is set in the
configured `threshold`:

```php
LogInterface::NONE       // 0   — nothing
LogInterface::EMERGENCY  // 1
LogInterface::ALERT      // 2
LogInterface::CRITICAL   // 4
LogInterface::ERROR      // 8
LogInterface::WARNING    // 16
LogInterface::NOTICE     // 32
LogInterface::INFO       // 64
LogInterface::DEBUG      // 128
LogInterface::ALL        // 255 — everything
```

Combine bits to enable exactly the levels you want:

```php
// config/log.php — warnings and worse
return [
    'threshold' => LogInterface::ERROR | LogInterface::CRITICAL | LogInterface::ALERT | LogInterface::EMERGENCY,
];
```

The default threshold is `NONE` (logging off) — turn it up in your environment config.

### Guarding expensive messages with `isLogEnabled()`

If building the log message is itself costly (concatenation, `var_export`, an array literal),
guard it so you don't build a value that will be thrown away:

```php
if (isLogEnabled('DEBUG')) {
    logMsg('DEBUG', __METHOD__, ['state' => var_export($bigState, true)]);
}
```

`isLogEnabled($level)` returns whether that level would actually be written. It memoises its answer
per level, so repeated checks are cheap. The framework's own hot paths use this pattern
throughout.

### Log configuration

The `log` config (defaults in `vendor/orange/framework/src/config/log.php`):

| Key | Default | Purpose |
|-----|---------|---------|
| `threshold` | `Log::NONE` | Bitmask of enabled levels. |
| `filepath` | `__ROOT__/var/logs/{Y-m-d}.log` | Where messages are written. |
| `permissions` | `0644` | File mode applied to the log file. |
| `line format` | `%timestamp %level %message %context\n` | Line template. |
| `timestamp format` | `Y-m-d H:i:s` | `date()` format for `%timestamp`. |

The logger creates the log directory and applies permissions as needed on first write. To send
logs elsewhere, register a PSR‑3 handler for the logger to forward to (see the
[Log reference](reference/log.md)).

## Error handling

### How exceptions become responses

During `preContainer()`, if a global `errorHandler()` and `exceptionHandler()` are defined (they
are, via the framework helpers), `Application` registers them with
`set_error_handler()`/`set_exception_handler()`. From then on:

- **PHP errors** are converted to `ErrorException` by `errorHandler()` (respecting
  `error_reporting()`), so they flow through the same path as exceptions.
- **Uncaught exceptions** go to `exceptionHandler()`, which hands them to
  `orange\framework\Error`.

`Error` pulls the message/code/trace off the throwable, finds the best matching error view, and
sends the response — then exits.

### Error views

`Error` searches environment‑ and request‑type‑specific view directories for a template matching
the status code, falling back progressively. For a 404 in development over HTML it looks for
something like:

```
errors/dev/html/404.php   →   errors/404.php   →   a built-in raw fallback
```

If no template is found, it renders a safe, HTML‑escaped raw fallback — as an HTML `<pre>`, JSON,
or plain text depending on the request type. The framework's own error views live in
`vendor/orange/framework/src/views/errors/`; add your own under a `views/errors/` directory on the
view path to customise them.

### The HTTP exception helpers

Rather than build an error response by hand, throw one of the global `show*()` helpers. Each
throws the matching framework HTTP exception, which the handler turns into the right status and
error page:

```php
show400();                 // 400 Bad Request
show401();                 // 401 Unauthorized
show403('Members only');   // 403 Forbidden (optional message)
show404();                 // 404 Not Found
show422();                 // 422 Unprocessable Entity
show429();                 // 429 Too Many Requests
show500();                 // 500 Internal Server Error
show503();                 // 503 Service Unavailable

redirect301('/new-home');  // 301 redirect (thrown, not sent inline)
```

These are control flow, not "errors" in the failure sense — `FourohfourController::index()` is
literally just `show404();`. Because they throw, they unwind the stack cleanly from wherever you
call them:

```php
public function show(string $id): string
{
    $post = $this->posts->find((int) $id);

    if (!$post) {
        show404();                     // stops here; renders the 404 page
    }

    return $this->view->render('post/show');
}
```

> **Production hardening.** If `error_reporting()` is set to `0`, the default `exceptionHandler()`
> skips the detailed error page for genuinely unexpected (non‑`Http*`) exceptions and returns a
> bare 500 — so internal details never leak. Deliberate `Http*` exceptions (like `show404()`)
> still render their normal page.

### JSON APIs

For API controllers, prefer the `JsonController` response helpers over `show*()` so the client
gets a JSON body: `notFoundResponse()` (404), `errorsResponse()` (422), etc. — see
[Controllers](05-controllers.md#the-response-helpers). Use `show*()` when you want the framework's
error‑view machinery.

## Summary

- Log with `logMsg('LEVEL', 'message', $context)`; guard costly messages with `isLogEnabled()`.
- Levels are a bitmask; set `threshold` in `config/log.php` to choose what's written (default:
  nothing).
- Uncaught exceptions and PHP errors flow through `Error`, which renders a matching error view.
- Throw `show404()` and friends for HTTP error responses; they're normal control flow.

---

[← Events](10-events.md) · [Manual index](README.md) · [Next: Security →](12-security.md)
