# Reference: Output

[← Reference index](README.md) · Guide: [Request & response](../09-request-and-response.md)

`orange\framework\Output implements OutputInterface` — a buffer for the whole response (body,
headers, status code, content type, charset). Nothing reaches the client until `send()`. Reach it
via `container()->output` (alias `response`) or the `output()` helper. Attached to
`BaseController` as `$this->output`. Fluent setters return `self`.

## Constants

```php
Output::NO            // 0  header replace mode: don't replace
Output::REPLACEALL    // 1
Output::REPLACEEXACT  // 2

Output::HTML          // 'text/html'
Output::JSON          // 'application/json'
Output::JSONOPTIONS       // hex-escaping json_encode flags
Output::PRETTYJSONOPTIONS // JSONOPTIONS | JSON_PRETTY_PRINT
```

## Body

| Method | Purpose |
|--------|---------|
| `write(string $string, bool $append = true): self` | Append to (or, with `false`, replace) the body |
| `get(): string` | Current body |
| `flush(): self` | Clear the body |
| `__toString(): string` | The body as a string |

The dispatcher calls `write()` with your controller's returned string; you rarely call it yourself.

## Status, content type, charset

| Method | Purpose |
|--------|---------|
| `responseCode(int $code): self` | Set HTTP status |
| `getResponseCode(): int` | Current status |
| `contentType(string $contentType, string $fallback = ''): self` | Set content type (accepts `'json'` shortcut) |
| `getContentType(): string` | Current content type |
| `charSet(string $charSet): self` | Set charset |
| `getCharSet(): string` | Current charset |

```php
$this->output->responseCode(201)->contentType('json')->charSet('utf-8');
```

## Headers

| Method | Purpose |
|--------|---------|
| `header(string $value, int $replace = self::NO, bool $prepend = false): self` | Add a header |
| `getHeaders(): array` | Current headers |
| `flushHeaders(): self` | Clear headers |

## Sending & redirects

| Method | Purpose |
|--------|---------|
| `send(bool\|int $exit = false): void` | Emit headers, status, and body (framework calls this for you) |
| `flushAll(): self` | Clear body **and** headers |
| `redirect(string $url, int $responseCode = -1, bool $exit = true): void` | Redirect; `-1` uses the configured default code |
| `forceHttps(): void` | Redirect HTTP → HTTPS, honouring the `allowed hosts` allowlist |

## Configuration (`output` config)

| Key | Default | Purpose |
|-----|---------|---------|
| `contentType` | `text/html` | Default content type |
| `charSet` | `utf-8` | Default charset |
| `default redirect code` | `301` | `redirect()` default |
| `force https` | `false` | Redirect to HTTPS |
| `allowed hosts` | `[]` | Host allowlist for HTTPS redirects (anti open‑redirect) |
| `enable cors` / `allowed cors` | `false` / `[]` | CORS + allowed origins |
| `access-control-allow-credentials` | `false` | Credentials header (cookie/HTTP‑auth APIs only) |
| `access-control-max-age` | `86400` | Preflight cache seconds |
| `access-control-allow-methods` | `GET, POST, PUT, DELETE, OPTIONS` | Allowed CORS methods |

> **Testability.** The real `header()`/`echo`/`exit` calls are wrapped in overridable protected
> methods, so `Output` can be exercised in unit tests without producing real output.

---

[← Reference index](README.md) · Guide: [Request & response](../09-request-and-response.md)
