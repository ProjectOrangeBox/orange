# 6. Views

[← Controllers](05-controllers.md) · [Manual index](README.md) · [Next: Configuration →](07-configuration.md)

Orange's view layer is deliberately plain: templates are `.php` files, data is `extract()`ed into
scope, and the file is `require`d. There is **no templating language and no compile step**. If you
know PHP, you know the view layer.

## The `view` service

The view engine is the `view` service (`orange\framework\View`, implementing `ViewInterface`). You
usually reach it from a controller via `#[AttachService('view')]`:

```php
#[AttachService('view')] protected ViewInterface $view;
#[AttachService('data')] protected DataInterface  $data;
```

Its public surface is small:

```php
public function render(string $view = '', array $data = [], array $options = []): string;
public function renderString(string $string, array $data = [], array $options = []): string;
public function change(string $name, mixed $value): self;
public function search(): DirectorySearchInterface;
```

## Rendering a template

`render('main/index')` locates `main/index.php` on the view search path, injects the data, and
returns the rendered HTML as a string:

```php
#[Route('*', '/', 'home')]
public function index(): string
{
    $this->data['name'] = 'Johnny Appleseed';
    return $this->view->render('main/index');   // -> .../views/main/index.php
}
```

The `.php` extension is implied (configurable). The view name is a path **relative to a directory
on the search path**, so `main/index` resolves to `<some view dir>/main/index.php`.

## Passing data to views

There are two ways to get variables into a template, and they compose:

**1. The `data` service (recommended).** Anything you put on `$this->data` is available in every
view rendered afterward. Set it one key at a time or many at once:

```php
// one at a time
$this->data['name']  = 'Johnny Appleseed';
$this->data['around'] = 'AROUND THE WEB';

// or many at once with merge()
$this->data->merge([
    'h1'       => $this->config['application']['h1'],
    'position' => $this->config['application']['position'],
    'cash'     => '19.95',
]);

return $this->view->render('main/index');
```

**2. The `$data` argument to `render()`.** Data passed directly to `render()` is merged for that
render only:

```php
return $this->view->render('main/index', ['cash' => '19.95']);
```

Inside the template, each key is a local variable. **Escape output** with the `e()` helper
(recursive `htmlspecialchars`) unless the value is trusted HTML. That includes attribute
values — `backslashDoubleQuotes()` is not an alternative for them, and is not an HTML
escaper at all:

```php
<!-- .../views/main/index.php -->
<h1><?= e($h1) ?></h1>
<p class="masthead-subheading"><?= e($position) ?></p>
<p>Ipsum dolor sit <?= e($cash) ?> amet ...</p>
```

Because the framework can't statically see these variables (they exist only at render time),
`phpstan.neon` and `rector.php` both exclude `application/*/views/*` from analysis. That's why
undefined‑variable warnings don't appear for views.

## Partials

A partial is just an `include`. Templates pull in shared fragments directly:

```php
<?php include __DIR__ . '/../partials/header.php' ?>
<?php include __DIR__ . '/../partials/nav.php' ?>

<!-- page content here -->

<?php include __DIR__ . '/../partials/footer.php' ?>
```

Included files share the same variable scope, so any data you set is available inside partials
too. There is no special partial API — it is plain PHP `include`.

## The view search path

Views are found by an internal directory search (`DirectorySearch`). Multiple directories can be
registered; when you `render('main/index')`, the engine looks for `main/index.php` across them and
uses the first match (front of the list wins).

The search path is populated from three sources:

1. **Config defaults** — `default view paths` (framework's `src/views` by default) and any
   `view paths` you set in the `view` config.
2. **Controller‑local views** — if a controller has a `$view` property, `BaseController` adds that
   controller's sibling `views/` directory at the **front** of the path (highest priority). This
   is why module controllers just work without configuring paths.
3. **Runtime additions** — `$this->view->search()->addDirectory($path)` lets you add directories
   on the fly. `search()` returns the [DirectorySearch](reference/view.md) object.

```php
// add a directory manually, at highest priority
$this->view->search()->addDirectory('/path/to/extra/views', DirectorySearch::FIRST);
```

## Rendering an ad‑hoc string

`renderString()` renders a template held in a string rather than a file — handy for content that
lives in the database or a config value:

```php
$html = $this->view->renderString('Hello, <?= e($name) ?>!', ['name' => 'World']);
```

Internally the string is compiled to a cached temp file (under the configured `temp directory`)
and executed like any other template, so the same data injection and PHP features apply.

## Runtime options with `change()`

`change($name, $value)` toggles a single view‑engine option at runtime and returns the engine for
chaining:

```php
$this->view
    ->change('debug', true)
    ->change('allow dynamic views', true);
```

Common options mirror the config keys below.

## View configuration

The `view` config (framework defaults in `vendor/orange/framework/src/config/view.php`, override
in your `config/view.php`):

| Key | Default | Purpose |
|-----|---------|---------|
| `view paths` | `[]` | Extra directories to search (highest of the configured ones). |
| `default view paths` | `[framework src/views]` | Fallback directories, searched last. |
| `view aliases` | `[]` | Map a view name to another (e.g. a shared layout). |
| `temp directory` | `sys_get_temp_dir()` | Where `renderString()` writes its compiled cache. |
| `debug` | `DEBUG` | Verbose view diagnostics. |
| `extension` | `.php` | Template file extension. |
| `allow dynamic views` | `false` | Enables `$c`/`$m`/`$1`/`$2` placeholders resolved from the matched route (controller/method/URL segments). |
| `sub path size` | `6` | Internal bucketing size for the temp cache. |

Dynamic views (`allow dynamic views`) let a view name include placeholders that the engine fills
from the current route — for example rendering `errors/$c` where `$c` is the matched controller
segment. It's off by default; enable it only if you use that pattern.

## Summary

- Templates are plain PHP; `render('path/name')` finds `path/name.php` and returns a string.
- Put shared data on the `data` service; pass per‑render data as `render()`'s second argument.
- Escape output with `e()` — including inside attributes.
- Partials are `include`; the search path is front‑wins, and a controller's `$view` property
  auto‑registers its module's `views/` at the front.

---

[← Controllers](05-controllers.md) · [Manual index](README.md) · [Next: Configuration →](07-configuration.md)
