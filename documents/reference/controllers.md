# Reference: Controllers

[← Reference index](README.md) · Guide: [Controllers](../05-controllers.md)

The framework ships four controller classes in `orange\framework\controllers\`. `BaseController`
and `JsonController` are the ones you extend.

## `BaseController` (abstract)

Base class for controllers. Its constructor wires the controller up; you never write your own
`__construct()`. Attaches `config`, `input`, `output` for you.

### Attached services

```php
#[AttachService('config')] protected ConfigInterface $config;
#[AttachService('input')]  protected InputInterface  $input;
#[AttachService('output')] protected OutputInterface $output;
```

### Constructor behavior

On construction, `BaseController`:

1. **Auto‑attaches** every property carrying `#[AttachService('name')]`, resolving `name` from the
   container.
2. **Includes libraries**: for each name in `protected array $libraries`, `include_once`s
   `<module>/libraries/<name>.php` (two directories up from the controller). Missing file →
   `FileNotFound`.
3. **Registers a local view directory**: if a `$view` property (typed `ViewInterface`) is present,
   adds the controller's sibling `views/` directory to the front of the view search path.
4. **Calls `beforeMethodCalled()`** if the subclass defines it.

### Overridable members

| Member | Purpose |
|--------|---------|
| `protected array $libraries = []` | Module‑local PHP files to `include_once` before the method runs |
| `protected function beforeMethodCalled(): void` | Optional setup hook run after wiring, before the routed method |

## `JsonController` (abstract, extends `BaseController`)

Base class for JSON/REST endpoints. Adds a `data` service and response helpers.

### Attached service & flags

```php
#[AttachService('data')] protected DataInterface $data;

// hex-escaping flags + JSON_THROW_ON_ERROR
protected int $jsonFlags;
```

### Response helpers

| Method | Status | Body |
|--------|--------|------|
| `response(int $status = 200, ?string $raw = null): string` | `$status` | `$raw`, else `$this->data` JSON‑encoded. Sets code + `application/json`. |
| `listResponse(array $list, int $status = 200): string` | `$status` | `array_values($list)` encoded — a top‑level JSON **array**. |
| `errorsResponse(array $errors, int $status = 422): string` | `422` | `{"errors": {…}}` keyed by field. |
| `notFoundResponse(string $msg = 'Not Found'): string` | `404` | `{"msg": "…"}`. |
| `noContentResponse(): string` | `204` | Empty body. |

All are `protected` — call them from within your controller and `return` the result.

```php
$this->data->id = $newId;
return $this->response(201);       // 201 {"id": ...}
```

## `HomeController` (concrete)

Default `/` handler. `index()` returns a placeholder welcome page. Replace by pointing the `home`
route at your own controller.

## `FourohfourController` (concrete)

Default catch‑all not‑found handler. `index()` calls `show404()`. Wired to the `(.*)` route in the
default `routes` config.

---

[← Reference index](README.md) · Guide: [Controllers](../05-controllers.md)
