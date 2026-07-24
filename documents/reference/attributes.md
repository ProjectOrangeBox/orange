# Reference: Attributes

[← Reference index](README.md) · Guide: [Routing](../04-routing.md), [Controllers](../05-controllers.md), [DI](../08-dependency-injection.md)

Three PHP attributes in `orange\framework\attributes\` drive declarative wiring instead of config
arrays.

## `#[Route]`

`Attribute::TARGET_METHOD` — marks a controller method as a route for the router to discover.

```php
public function __construct(
    public string|array $method = [],   // 'GET', ['GET','POST'], or '*'
    public string       $url = '',      // URL pattern (regex; capture groups → args)
    public string       $name = '',     // route name for getUrl()
)
```

```php
use orange\framework\attributes\Route;

#[Route('GET', '/user/(\d+)/view', 'users.show')]
public function show(string $id): string { /* $id from (\d+) */ }

#[Route(['GET', 'POST'], '/users', 'users.index')]
public function index(): string { /* ... */ }

#[Route('*', '/', 'home')]
public function home(): string { /* every method in "match all" */ }
```

Discovered by `RouterDetector` from your module directories (in development) or exported to
`config/production/routes.php` (for production). See [Routing](../04-routing.md).

## `#[AttachService]`

`Attribute::TARGET_PROPERTY` — marks a controller property to be populated from the container.

```php
public function __construct(public string $attachService)   // the service name
```

```php
use orange\framework\attributes\AttachService;
use orange\framework\interfaces\ViewInterface;

class MainController extends BaseController
{
    #[AttachService('view')] protected ViewInterface $view;
    #[AttachService('data')] protected DataInterface  $data;
}
```

Read by **`BaseController`** (not the container) during construction — so it only works on classes
extending `BaseController`. See [Controllers](../05-controllers.md#injecting-services-with-attachservice).

## `#[AutoWire]`

`Attribute::TARGET_METHOD` — stacked once per positional argument on a constructor or `getInstance()`
method, in declaration order, to resolve each argument from the container when the class is
auto‑wired.

```php
public function __construct(public string $service)   // the service name for one argument
```

```php
use orange\framework\attributes\AutoWire;

class MyService
{
    #[AutoWire('pdo')]
    #[AutoWire('log')]
    public function __construct(protected PDO $pdo, protected LogInterface $log) { /* ... */ }
}
```

Read by the **container** when resolving a service registered with the `^` prefix
(`$container->set('^myService', MyService::class)`). The number and order of `#[AutoWire]`
attributes must match the method's parameters. See
[Container → Auto‑wiring](container.md#auto-wiring).

---

[← Reference index](README.md) · Guide: [Routing](../04-routing.md), [Controllers](../05-controllers.md), [DI](../08-dependency-injection.md)
