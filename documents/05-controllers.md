# 5. Controllers

[← Routing](04-routing.md) · [Manual index](README.md) · [Next: Views →](06-views.md)

A controller is a plain class whose public methods handle routes. Each handler **returns a
string** — the response body. Orange gives you two optional base classes: `BaseController` for
HTML pages and `JsonController` for APIs. You are not required to extend either, but they remove
almost all the boilerplate.

## The minimum

A controller doesn't have to extend anything. A method with a `#[Route]` that returns a string is
a complete handler. But without a base class you get no service injection, so in practice you
extend `BaseController`.

## `BaseController`

`orange\framework\controllers\BaseController` is an abstract class that, in its constructor:

1. **Auto‑attaches services** declared with `#[AttachService('name')]` on typed properties.
2. **Includes controller‑local libraries** listed in `protected array $libraries`.
3. **Registers a controller‑local `views/` directory** if the controller has a `$view` property.
4. **Calls `beforeMethodCalled()`** if your controller defines it.

It attaches three services for you out of the box — `config`, `input`, and `output`:

```php
abstract class BaseController
{
    #[AttachService('config')] protected ConfigInterface $config;
    #[AttachService('input')]  protected InputInterface  $input;
    #[AttachService('output')] protected OutputInterface $output;
    // ...
}
```

So inside any controller extending it you can immediately use `$this->config`, `$this->input`,
and `$this->output`.

### Injecting services with `#[AttachService]`

To pull any other service out of the [container](08-dependency-injection.md), declare a typed
property and annotate it:

```php
use orange\framework\attributes\AttachService;
use orange\framework\interfaces\ViewInterface;
use orange\framework\interfaces\DataInterface;

class MainController extends BaseController
{
    #[AttachService('view')] protected ViewInterface $view;
    #[AttachService('data')] protected DataInterface $data;

    #[Route('*', '/', 'home')]
    public function index(): string
    {
        $this->data['name'] = 'Johnny Appleseed';
        return $this->view->render('main/index');
    }
}
```

The string in `#[AttachService('...')]` is the **service name** in the container — `'view'`,
`'data'`, `'log'`, `'router'`, or any service you registered yourself (see below). No constructor,
no manual `container()->get()`. The attribute is read by `BaseController`, not by the container,
so it only works on classes that extend `BaseController`.

### Controller‑local libraries

Orange has no separate "model" or "helper" base class. Instead, list plain PHP files to
`include_once` before the controller runs:

```php
class Report extends BaseController
{
    protected array $libraries = ['formatters', 'calculations'];
    // ...
}
```

Each name (no `.php`) is loaded from `<module>/libraries/<name>.php` — that is, the `libraries/`
directory two levels up from the controller file. If a listed file is missing, the constructor
throws `FileNotFound`. Use this for procedural helpers or model files scoped to one module.

### The controller‑local view directory

If your controller declares a `$view` property (typed `ViewInterface`), `BaseController` finds the
sibling `views/` directory — two levels up from the controller, i.e. `.../controllers/Foo.php` →
`.../views` — and adds it to the **front** of the view engine's search path. That is why
`MainController` in `application/welcome/controllers/` can `render('main/index')` and hit
`application/welcome/views/main/index.php` with no path configuration. See [Views](06-views.md).

### The `beforeMethodCalled()` hook

If your controller defines a `protected function beforeMethodCalled(): void`, `BaseController`
calls it at the end of construction — after services are attached and libraries loaded, but
before the routed method runs. Use it for per‑controller setup (auth checks, shared data) instead
of writing a constructor:

```php
class Admin extends BaseController
{
    #[AttachService('data')] protected DataInterface $data;

    protected function beforeMethodCalled(): void
    {
        $this->data['section'] = 'admin';
        // e.g. require a logged-in user, or show403();
    }
}
```

You never write a real `__construct()` in a controller — `Dispatcher` constructs the controller
for you, and `BaseController`'s constructor does the wiring.

## `JsonController`

`orange\framework\controllers\JsonController` extends `BaseController` for JSON/REST endpoints. It
adds a `data` service and helper methods that set the response code + JSON content type and return
the encoded body.

```php
abstract class JsonController extends BaseController
{
    #[AttachService('data')] protected DataInterface $data;

    protected int $jsonFlags = /* hex-escaping flags | JSON_THROW_ON_ERROR */;
}
```

### The response helpers

| Method | Status | Returns |
|--------|--------|---------|
| `response(int $status = 200, ?string $raw = null)` | `$status` | `$raw` if given, else `$this->data` JSON‑encoded. Sets code + `application/json`. |
| `listResponse(array $list, int $status = 200)` | `$status` | `array_values($list)` encoded — a **top‑level JSON array** rather than an object. |
| `errorsResponse(array $errors, int $status = 422)` | `422` | `{"errors": {…}}` — validation failures keyed by field. |
| `notFoundResponse(string $msg = 'Not Found')` | `404` | `{"msg": "…"}`. |
| `noContentResponse()` | `204` | Empty body (the one success response with no body). |

`$this->data` is an [ArrayObject](reference/data.md)‑backed store; assign to it and `response()`
encodes it:

```php
$this->data->id = 42;          // property style
$this->data['name'] = 'Ada';   // array style
return $this->response(201);   // 201 {"id":42,"name":"Ada"}
```

Note that because `$this->data` encodes to a JSON **object**, use `listResponse()` (or pass a
`$raw` string) when you need a top‑level array.

### A full REST controller

From `api/controllers/RestController.php` (abridged):

```php
class RestController extends JsonController
{
    #[AttachService('RecordModel')]
    protected RecordModel $recordModel;

    #[Route('get', '/api/index', 'rest_index')]
    public function index(): string
    {
        return $this->listResponse($this->recordModel->index());   // 200 [ {...}, {...} ]
    }

    #[Route('get', '/api/read/(\d+)', 'rest_read')]
    public function read(string $id): string
    {
        $record = $this->recordModel->read((int) $id);

        if (!$record instanceof RecordDto) {
            return $this->notFoundResponse('Record not found');    // 404 {"msg": ...}
        }

        return $this->response(200, json_encode($record, $this->jsonFlags));
    }

    #[Route('post', '/api/create', 'rest_create')]
    public function create(): string
    {
        $record = new RecordDto($this->input->request());          // read parsed request body

        if (!$record->isValid()) {
            return $this->errorsResponse($record->allErrors());    // 422 {"errors": {...}}
        }

        $this->data->id = $this->recordModel->create($record);
        return $this->response(201);                               // 201 {"id": n}
    }

    #[Route('delete', '/api/delete/(\d+)', 'rest_delete')]
    public function delete(string $id): string
    {
        if (!$this->recordModel->exists((int) $id)) {
            return $this->notFoundResponse('Record not found');
        }
        $this->recordModel->delete((int) $id);
        return $this->noContentResponse();                         // 204 (no body)
    }
}
```

Note `#[AttachService('RecordModel')]` — a domain model registered as your own service in
`config/services.php` (see [Dependency injection](08-dependency-injection.md)), injected the same
way as framework services. `$this->input->request()` returns the parsed request body (JSON or
form); see [Request & response](09-request-and-response.md).

## The default controllers

The framework ships two controllers wired into the default route config:

- **`HomeController`** — handles `/`. Its `index()` returns a placeholder welcome page. Replace it
  by pointing the `home` route at your own controller.
- **`FourohfourController`** — the catch‑all not‑found handler. Its `index()` just calls
  `show404()`, which throws `Http404` and produces the framework's 404 page.

## HMVC: modules

Each top‑level PSR‑4 root (`application\` → `application/`, `api\` → `api/`) is an independent MVC
**module** with its own `controllers/`, `views/`, and optional `libraries/`. Modules can nest
arbitrarily deep — `application/welcome` is itself a nested sub‑module. Modules depend only on
shared services (router, view, data, …), never on each other's controllers or views. To add a
module: create the folder, add a PSR‑4 entry in `composer.json`, run `composer dump-autoload`, and
register its path with `RouterDetector` (see [Routing](04-routing.md)).

## Summary

- Extend `BaseController` for HTML, `JsonController` for APIs — or neither, if you don't need
  injection.
- Inject any service with `#[AttachService('name')]` on a typed property.
- `config`, `input`, `output` are attached for you; `data` too on `JsonController`.
- `$libraries` includes module‑local PHP files; a `$view` property auto‑registers the module's
  view directory; `beforeMethodCalled()` runs before the routed method.
- Every handler returns a **string**.

---

[← Routing](04-routing.md) · [Manual index](README.md) · [Next: Views →](06-views.md)
