# Reference: Input

[← Reference index](README.md) · Guide: [Request & response](../09-request-and-response.md)

`orange\framework\Input implements InputInterface` (which `extends ArrayAccess`) — an immutable
snapshot of the request. Captures `$_GET`/`$_POST`/`$_SERVER`/`$_COOKIE`/`$_FILES` and the raw
body once (`Input::fromGlobals()`), then unsets the superglobals. Reach it via `container()->input`
(alias `request`) or the `input()` helper. Attached to `BaseController` as `$this->input`.

## Data accessors

Each takes an optional key (returns the whole collection when omitted) and a default:

| Method | Reads |
|--------|-------|
| `query(?string $key = null, mixed $default = null): mixed` | `$_GET` |
| `request(?string $key = null, mixed $default = null): mixed` | Parsed request body (POST/PUT, JSON or urlencoded) |
| `server(?string $key = null, mixed $default = null): mixed` | `$_SERVER` (normalised keys) |
| `header(?string $key = null, mixed $default = null): mixed` | Request headers |
| `cookie(?string $key = null, mixed $default = null): mixed` | `$_COOKIE` |
| `file(null\|int\|string $key = null, mixed $default = null): mixed` | `$_FILES` |

```php
$page  = $this->input->query('page', 1);
$body  = $this->input->request();               // whole parsed body
$ctype = $this->input->header('Content-Type');
```

## Request metadata

| Method | Returns |
|--------|---------|
| `requestUri(): string` | The request path |
| `uriSegment(int $n): string` | The Nth path segment |
| `getUrl(int $component = -1)` | A parsed URL component (use the `PHP_URL_*` constants below); `-1` = full |
| `requestMethod(bool $asLowercase = true): string` | HTTP method (honours a `_method` override) |
| `requestType(bool $asLowercase = true): string` | `ajax` / `cli` / `html` / … |
| `contentType(bool $asLowercase = true): string` | Request content type |

## Predicates

| Method | True when |
|--------|-----------|
| `isAjaxRequest(): bool` | `X-Requested-With: XMLHttpRequest` |
| `isCliRequest(): bool` | Running under CLI |
| `isHttpsRequest(bool $asString = false): bool\|string` | Request over HTTPS |

## Constants

HTTP methods: `GET POST PUT DELETE HEAD OPTIONS TRACE CONNECT`.

URL components (aliases of PHP's `PHP_URL_*`): `SCHEME HOST PORT USER PASS PATH QUERY FRAGMENT`.

```php
$host = $this->input->getUrl(Input::HOST);
```

## Array access

`InputInterface extends ArrayAccess`. Read collections by offset:

```php
$contentType = $this->input['server']['content_type'];
```

Writing or unsetting an offset **throws** — the request is an immutable snapshot.

## Configuration

The `input` service is built via `Input::fromGlobals()` (see
`vendor/orange/framework/src/config/input.php`), capturing the current request. In tests you build
an `Input` from a supplied array instead of the live globals.

---

[← Reference index](README.md) · Guide: [Request & response](../09-request-and-response.md)
