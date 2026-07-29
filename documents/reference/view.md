# Reference: View

[← Reference index](README.md) · Guide: [Views](../06-views.md)

`orange\framework\View implements ViewInterface` — the plain‑PHP template engine (a concrete
subclass of `ViewAbstract`). Locates templates via an internal `DirectorySearch`, injects data,
and renders. Singleton, built from the `view` config plus the `data` and `router` services. Reach
it via `container()->view` or `$this->view` on a controller that attaches it.

## Methods

### `render(string $view = '', array $data = [], array $options = []): string`

Find `<$view>.php` on the search path, `extract()` the shared `data` plus `$data`, `require` the
template, and return the captured output.

```php
return $this->view->render('main/index', ['cash' => '19.95']);
```

### `renderString(string $string, array $data = [], array $options = []): string`

Render a template held in a string. The string is compiled to a cached temp file (under
`temp directory`) and executed like any template.

```php
$html = $this->view->renderString('Hello, <?= e($name) ?>!', ['name' => 'World']);
```

### `change(string $name, mixed $value): self`

Set a single engine option at runtime (mirrors the config keys). Chainable.

```php
$this->view->change('debug', true)->change('tempDirectory', $dir);
```

### `search(): DirectorySearchInterface`

The `DirectorySearch` managing the view path. Use it to add/remove directories:

```php
$this->view->search()->addDirectory('/extra/views', DirectorySearch::FIRST);
```

## The view search path

Templates are resolved across registered directories, **front wins**. Populated from:

1. Config `view paths` and `default view paths`.
2. A controller's sibling `views/` directory (added at the front by `BaseController` when the
   controller has a `$view` property).
3. Runtime `search()->addDirectory(...)` calls.

`DirectorySearchInterface` constants: `FIRST`/`PREPEND` (= 1) and `LAST`/`APPEND` (= 2) control
where a directory is inserted.

## Configuration (`view` config)

| Key | Default | Purpose |
|-----|---------|---------|
| `view paths` | `[]` | Extra directories (highest of the configured) |
| `default view paths` | `[framework src/views]` | Fallback directories |
| `view aliases` | `[]` | Map a view name to another |
| `temp directory` | `sys_get_temp_dir()` | `renderString()` cache location |
| `debug` | `DEBUG` | Verbose diagnostics |
| `extension` | `.php` | Template extension |
| `sub path size` | `6` | Temp cache bucketing |

## Route-derived view names

`$c` (controller), `$m` (method) and `$1`/`$2` (namespace segments) are resolved by
[`BaseController::renderView()`](../06-views.md#route-derived-view-names), not by the view engine —
which takes no router and knows nothing about routing.

---

[← Reference index](README.md) · Guide: [Views](../06-views.md)
