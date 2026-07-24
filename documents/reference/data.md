# Reference: Data

[← Reference index](README.md) · Guide: [Views](../06-views.md)

`orange\framework\Data implements DataInterface` (which `extends ArrayAccess`) — a shared,
`ArrayObject`‑backed data store built with `ARRAY_AS_PROPS`, so it supports both property and array
access. It's used to pass data into views and to share state between services and controllers
without a global. Singleton. Reach it via `container()->data` or `$this->data` on a controller that
attaches it.

## Access

Both styles are part of the contract and are equivalent:

```php
$this->data['name'] = 'Johnny';   // array access
$this->data->name   = 'Johnny';   // property access (__set)

$name = $this->data['name'];      // read (missing → null)
$name = $this->data->name;        // read (missing → null)
```

## Methods

### `merge(array $array, bool $recursive = true, bool $replace = true): static`

Merge an array into the store. Recursive and replacing by default.

```php
$this->data->merge([
    'h1'       => 'Hello World!',
    'position' => 'Head Bottle Washer',
    'cash'     => '19.95',
]);
```

### `get(string $name, mixed $default): mixed`

Read a value, falling back to `$default` when the key isn't set.

### `has(string $name): bool`

Whether a value is set under `$name`.

### `__get(string $name): mixed` / `__set(string $name, mixed $value): void`

Property read/write. Reading a missing name returns `null` rather than raising.

## Relationship to views

Anything on the `data` service is `extract()`ed into scope when a template renders, so
`$this->data['name'] = 'X'` makes `$name` available in the view. Data passed directly to
`View::render($view, $data)` is merged for that render only. See [Views](../06-views.md).

## Relationship to `JsonController`

`JsonController` attaches `data` as `$this->data` and JSON‑encodes it in `response()`. Because the
store encodes to a JSON **object**, use `listResponse()` (or pass a raw string) when you need a
top‑level JSON array. See [Controllers](../05-controllers.md#jsoncontroller).

---

[← Reference index](README.md) · Guide: [Views](../06-views.md)
