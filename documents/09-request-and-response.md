# 9. Request & response

[← Dependency injection](08-dependency-injection.md) · [Manual index](README.md) · [Next: Events →](10-events.md)

Two services model the HTTP exchange: **`input`** wraps the incoming request, and **`output`**
buffers the outgoing response. Both are attached to every `BaseController` (`$this->input`,
`$this->output`) and both are designed to be testable — `input` is an immutable snapshot, and
`output` doesn't touch real headers/echo until `send()`.

## The `input` service

`orange\framework\Input` (implementing `InputInterface`) captures the superglobals
(`$_GET`, `$_POST`, `$_SERVER`, `$_COOKIE`, `$_FILES`) and the raw body **once**, at construction,
and then unsets them from PHP. From then on it is a read‑only snapshot of the request.

### Reading request data

Each accessor takes an optional key and default. With no key you get the whole collection:

```php
$this->input->query('page', 1);       // $_GET['page'] or 1
$this->input->request('name');        // parsed request body value (POST/PUT/JSON)
$this->input->request();              // the whole parsed body as an array
$this->input->server('REQUEST_URI');  // normalised $_SERVER value
$this->input->header('Content-Type'); // a request header
$this->input->cookie('session');      // a cookie
$this->input->file('upload');         // a $_FILES entry
```

`request()` returns the **parsed body**: the framework decodes JSON or urlencoded bodies for you,
so a JSON API controller reads posted fields the same way as a form. That's why the REST example
does `new RecordDto($this->input->request())`.

### Request metadata

```php
$this->input->requestUri();                 // '/api/read/42'
$this->input->uriSegment(2);                // the Nth path segment
$this->input->requestMethod();              // 'get' (honours a _method override for forms)
$this->input->requestType();                // 'ajax' | 'cli' | 'html' | ...
$this->input->contentType();                // 'application/json'
$this->input->getUrl(Input::PATH);          // a parsed URL component (uses PHP_URL_* consts)
```

Convenience predicates:

```php
$this->input->isAjaxRequest();     // X-Requested-With: XMLHttpRequest
$this->input->isCliRequest();      // running under CLI
$this->input->isHttpsRequest();    // request came over HTTPS
```

`requestMethod()` honours a `_method` override, so an HTML form can `POST` with a hidden
`_method=PUT` field and route as a `PUT`.

### Array access

`InputInterface extends ArrayAccess`, so you can read collections by offset as an alternative to
the methods:

```php
$contentType = $this->input['server']['content_type'];
```

Writing (`$input[...] = …`) or unsetting **throws** — the request is an immutable snapshot, not
mutable state.

## The `output` service

`orange\framework\Output` (implementing `OutputInterface`) is a buffer for the whole response:
body, headers, status code, content type, charset. Nothing reaches the client until `send()` runs
(the framework calls `send()` for you in the lifecycle). Because the real `header()`/`echo`/`exit`
calls are wrapped in overridable protected methods, output is fully unit‑testable.

### Body

```php
$this->output->write('some html');           // append to the body
$this->output->write('replace', false);      // replace the body
$this->output->get();                         // current body string
$this->output->flush();                       // clear the body
```

In normal request flow you don't call `write()` yourself — the dispatcher writes your controller's
returned string. You interact with `output` mainly to set the **status code**, **content type**,
and **headers** before returning the body.

### Status, content type, charset

All of these return `$this` for chaining:

```php
$this->output
    ->responseCode(201)
    ->contentType('application/json')   // or the shortcut 'json'
    ->charSet('utf-8');
```

`OutputInterface` provides constants for the common content types:

```php
Output::HTML   // 'text/html'
Output::JSON   // 'application/json'
```

`JsonController::response()` does exactly `$this->output->responseCode($status)->contentType('json')`
for you — you rarely set these by hand in a JSON controller.

### Headers

```php
$this->output->header('X-Custom: value');
$this->output->header('Cache-Control: no-store', Output::REPLACEALL);
$this->output->getHeaders();     // current headers
$this->output->flushHeaders();   // clear them
```

The second argument controls replacement (`NO`, `REPLACEALL`, `REPLACEEXACT`).

### Redirects

```php
$this->output->redirect('/login');          // 301 (configurable default), then exit
$this->output->redirect('/somewhere', 302);
```

There is also the [`redirect301()` global helper](13-helpers.md), which throws an `Http301`
exception routed through the error handler — useful when you want to redirect from deep in a call
stack.

### Forcing HTTPS

`forceHttps()` redirects an HTTP request to HTTPS. To prevent a Host‑header‑injection open
redirect, it only trusts the request's `Host` header if that host appears in the `allowed hosts`
allowlist. Enable it via config, not by calling it ad hoc:

```php
// config/output.php
return [
    'force https'   => true,
    'allowed hosts' => ['example.com', 'www.example.com'],
];
```

When `allowed hosts` is empty, the request Host is never trusted.

### Sending

```php
$this->output->send();   // emit headers, status code, and body
```

The framework calls this once per request. You normally never call it yourself.

## Output configuration

The `output` config (defaults in `vendor/orange/framework/src/config/output.php`):

| Key | Default | Purpose |
|-----|---------|---------|
| `contentType` | `text/html` | Default response content type. |
| `charSet` | `utf-8` | Default charset. |
| `default redirect code` | `301` | Status used by `redirect()` when unspecified. |
| `force https` | `false` | Redirect HTTP → HTTPS. |
| `allowed hosts` | `[]` | Host allowlist for `force https` / redirects (anti‑open‑redirect). |
| `enable cors` / `allowed cors` | `false` / `[]` | CORS handling and allowed origins. |
| `access-control-allow-credentials` | `false` | Send credentials header for allowed origins (cookie/HTTP‑auth APIs only). |
| `access-control-max-age` | `86400` | CORS preflight cache seconds. |
| `access-control-allow-methods` | `GET, POST, PUT, DELETE, OPTIONS` | Allowed CORS methods. |

## Typical patterns

**Return an HTML page** (status defaults to 200):

```php
return $this->view->render('main/index');
```

**Return JSON with a status:**

```php
$this->data->id = $newId;
return $this->response(201);            // JsonController helper
```

**Set a custom header, then return a body:**

```php
$this->output->header('X-Total-Count: 128');
return $this->listResponse($rows);
```

**Redirect:**

```php
$this->output->redirect(getUrl('home'));   // resolve the target with getUrl, then redirect
```

## Summary

- `input` is an immutable snapshot of the request; read with `query()`, `request()`, `server()`,
  `header()`, `cookie()`, `file()`, plus metadata/predicate methods. Writes throw.
- `request()` returns the parsed body (JSON or form) — the same for APIs and HTML forms.
- `output` buffers body + headers + status; set them (chainably) before returning the body.
- The framework writes your returned string and calls `send()` for you.

---

[← Dependency injection](08-dependency-injection.md) · [Manual index](README.md) · [Next: Events →](10-events.md)
