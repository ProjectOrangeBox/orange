# 2. Getting started

[← Introduction](01-introduction.md) · [Manual index](README.md) · [Next: The request lifecycle →](03-request-lifecycle.md)

This chapter gets a request flowing end to end: the project layout, the single entry point, a
"hello world" route, and how to run the app locally.

## Project layout

An Orange application is a normal Composer project. The framework is a dependency; your code
lives at the project root:

```
your-app/
├── htdocs/                 # public web root — the ONLY web-served directory
│   └── index.php           # the single entry point
├── application/            # an MVC module (PSR-4 root "application\")
│   └── welcome/
│       ├── controllers/
│       └── views/
├── api/                    # another MVC module (PSR-4 root "api\")
│   ├── controllers/
│   └── models/
├── config/                 # your application configuration
│   ├── services.php
│   ├── application.php
│   ├── event.php
│   ├── development/        # overrides when ENVIRONMENT=development
│   │   ├── routes.php
│   │   └── RouterDetector.php
│   └── production/         # overrides when ENVIRONMENT=production
│       └── routes.php
├── .env                    # environment config (INI, gitignored)
├── vendor/
│   └── orange/framework/   # the framework package (this manual's subject)
└── composer.json
```

Two rules matter most here:

1. **Only `htdocs/` is web‑served.** Everything else — config, controllers, `.env`, `vendor/` —
   sits above the web root so it can never be requested directly.
2. **Each top‑level folder with a PSR‑4 root is a self‑contained MVC module.** `application/` and
   `api/` each have their own `controllers/` and `views/`. Modules share services but never
   reach into each other's controllers. This is the framework's [HMVC](05-controllers.md) layout.

## The entry point

Every request enters through `htdocs/index.php`. It does almost nothing itself:

```php
<?php
declare(strict_types=1);

use orange\framework\Application;

// All paths are anchored to this root, which sits one level above the web root.
define('__ROOT__', realpath(__DIR__ . '/../'));
define('__WWW__', __ROOT__ . '/htdocs');

// optional project bootstrap
if (file_exists(__ROOT__ . '/bootstrap.php')) {
    require_once __ROOT__ . '/bootstrap.php';
}

// Composer autoloader
require_once __ROOT__ . '/vendor/autoload.php';

// build the application and run the HTTP lifecycle
Application::make([__ROOT__ . '/.env'])->http();
```

`Application::make()` takes a list of environment files and (optionally) a list of config
directories, and returns the singleton `Application`. Calling `->http()` runs the full request
pipeline described in [Chapter 3](03-request-lifecycle.md).

> **Why no config directory is passed.** When you don't pass config directories, `Application`
> uses `config/` **and** `config/{ENVIRONMENT}/` automatically. Passing `config/` explicitly
> would *replace* that default and silently disable the per‑environment override folder. Leave it
> off unless you know you want to override the search path. See [Chapter 7](07-configuration.md).

## Hello, world

A route is just a controller method with a `#[Route]` attribute. Create
`application/welcome/controllers/Hello.php`:

```php
<?php
declare(strict_types=1);

namespace application\welcome\controllers;

use orange\framework\attributes\Route;
use orange\framework\controllers\BaseController;

class Hello extends BaseController
{
    #[Route('GET', '/hello', 'hello')]
    public function index(): string
    {
        return '<h1>Hello, world!</h1>';
    }
}
```

That is a complete, working page. The controller method **returns a string**; the framework
writes that string to the response body and sends it. The three arguments to `#[Route]` are the
HTTP method, the URL, and a route **name** (`'hello'`) you can later resolve with
`getUrl('hello')`.

In development, the router discovers this attribute automatically (see
[Routing](04-routing.md)); you do not edit a route table by hand.

## Rendering a view instead

Returning HTML from a string is fine for one line. For real pages, render a view. Add a `$view`
property and a `$data` property, and Orange wires them up for you:

```php
<?php
declare(strict_types=1);

namespace application\welcome\controllers;

use orange\framework\attributes\AttachService;
use orange\framework\attributes\Route;
use orange\framework\controllers\BaseController;
use orange\framework\interfaces\DataInterface;
use orange\framework\interfaces\ViewInterface;

class Hello extends BaseController
{
    #[AttachService('view')]
    protected ViewInterface $view;

    #[AttachService('data')]
    protected DataInterface $data;

    #[Route('GET', '/hello', 'hello')]
    public function index(): string
    {
        $this->data['name'] = 'World';

        // renders application/welcome/views/hello.php
        return $this->view->render('hello');
    }
}
```

`application/welcome/views/hello.php`:

```php
<h1>Hello, <?= e($name) ?>!</h1>
```

Because the controller has a `$view` property, `BaseController` automatically adds this
controller's sibling `views/` directory to the view search path. The `$name` variable is the key
you set on `$this->data`. See [Views](06-views.md) for the full story.

## Running the app locally

**With Docker** (the bundled setup):

```bash
docker compose up -d --build      # serves at http://localhost:8080
```

The entrypoint seeds `.env` from `support/samples/sample.env` on first run and installs `vendor/`
into the working copy. It mounts the repo live, so code edits need no rebuild — only
Dockerfile/dependency changes do.

**With PHP's built‑in server** (no Docker):

```bash
php -S 127.0.0.1:8000 -t htdocs
```

Point the document root at `htdocs/` either way, so only the public directory is reachable.

## The `.env` file

`.env` is an INI file, gitignored, holding environment‑specific values — at minimum the
environment name:

```ini
ENVIRONMENT = development
DEBUG = true

[db]
host     = localhost
database = myapp
username = root
password = secret
```

Read values anywhere in your code with the global `env()` helper:

```php
$dbHost = env('db')['host'] ?? 'localhost';
```

`ENVIRONMENT` defaults to `production` and `DEBUG` to `false` when not set — a safe default if
`.env` is missing in production. [Chapter 7](07-configuration.md) covers this in detail.

## Where to go next

- To understand exactly what `->http()` does, read [The request lifecycle](03-request-lifecycle.md).
- To add more routes, read [Routing](04-routing.md).
- To build JSON APIs, jump to [Controllers → JsonController](05-controllers.md#jsoncontroller).

---

[← Introduction](01-introduction.md) · [Manual index](README.md) · [Next: The request lifecycle →](03-request-lifecycle.md)
