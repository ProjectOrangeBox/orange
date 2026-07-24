# 8. Dependency injection

[← Configuration](07-configuration.md) · [Manual index](README.md) · [Next: Request & response →](09-request-and-response.md)

Every service in Orange — the router, the view engine, your own models — lives in one
dependency‑injection **container**. Services are registered as closures and built lazily on first
use, then (where appropriate) cached as singletons. This chapter shows how to register, retrieve,
and auto‑wire services.

## The container

`orange\framework\Container` (implementing `ContainerInterface`) is a singleton built during
bootstrap from `services.php`. You reach it three ways:

```php
container();                     // global helper — the container instance
container()->router;             // property access — resolves the 'router' service
container()->get('router');      // method access — identical
```

Inside a controller you rarely touch the container directly; you use
[`#[AttachService]`](05-controllers.md#injecting-services-with-attachservice) instead. But the
container is what makes that work.

## `services.php`: registering services

Services are declared in a `services.php` file that returns an array of `name => definition`. The
framework's own services live in `vendor/orange/framework/src/config/services.php`; your app adds
its services in `config/services.php`. Both are merged through the config cascade.

Here is the framework's own service definition file, which is also the canonical example of every
registration style:

```php
return [
    // aliases
    '@event'    => 'events',
    '@request'  => 'input',
    '@response' => 'output',

    // a plain value
    '$mimes' => include_once __DIR__ . '/mimes.php',

    // the container itself
    'container' => Container::getInstance(...),

    // closures — lazily built, receive the container
    'config'     => fn($c) => Config::getInstance($c->get('$application')),
    'log'        => fn($c) => Log::getInstance($c->config->log),
    'events'     => fn($c) => Event::getInstance($c->config->event),
    'input'      => fn($c) => Input::getInstance($c->config->input),
    'output'     => fn($c) => Output::getInstance($c->config->output, $c->input),
    'router'     => fn($c) => Router::getInstance($c->config->routes, $c->input),
    'data'       => fn($c) => Data::getInstance($c->config->data),
    'view'       => fn($c) => View::getInstance($c->config->view, $c->data, $c->router),
    'dispatcher' => Dispatcher::getInstance(...),
];
```

### Why closures?

Because a closure isn't run until the service is first requested, nothing is built until it's
needed. An app that never queries the database never opens a connection; an app that never renders
a view never constructs the view engine. This makes apps faster and services easy to mock in
tests. The closure receives the container as its only argument, so a service can pull in its own
dependencies (`$c->input`, `$c->config`).

## The four registration styles

`Container::set($name, $definition)` (and the `$container->name = …` form) dispatches on the name
prefix and value type:

| Style | How to register | When it resolves | Use for |
|-------|-----------------|------------------|---------|
| **Closure** | `'name' => fn($c) => …` | Called on first `get()`, container passed in | Almost everything — lazy services |
| **Value / object** | `'name' => $value` or a prebuilt object | Returned as‑is | Constants, arrays, pre‑built objects |
| **Alias** | `'@alias' => 'target'` | Resolves through to `target` | A second name for an existing service |
| **Auto‑wire class** | `'^name' => Some::class` | Reflection builds the class on first `get()` | Classes with `#[AutoWire]` constructors |

### Closures

The workhorse. The closure gets the container and returns the service:

```php
'uuid' => fn() => bin2hex(random_bytes(16)),
'pdo'  => function () {
    $db = env('db');
    return new PDO("mysql:host={$db['host']};dbname={$db['database']}", $db['username'], $db['password']);
},
```

### Values and objects

Anything that isn't a closure (and whose name has no special prefix) is stored and returned
verbatim:

```php
'$mimes'   => include_once __DIR__ . '/mimes.php',   // an array
'appName'  => 'My Application',                        // a scalar
```

By convention, value services are often named with a `$` prefix (`$mimes`, `$application`) to make
them visually distinct from lazy services, but the prefix is not required — `$` names are just
ordinary service names.

### Aliases (`@`)

A name beginning with `@` registers an alias pointing at another service name:

```php
'@event'    => 'events',    // container()->event is the same service as container()->events
'@request'  => 'input',
'@response' => 'output',
```

Alias chains are resolved with loop protection (max depth 16).

### Auto‑wired classes (`^`)

A name beginning with `^` registers a fully‑qualified class name for reflection‑based
construction:

```php
$container->set('^myService', \App\MyService::class);
$service = $container->get('myService');   // built via reflection on first get
```

When resolved, the container reflects on the class. It uses the **public constructor** if there is
one; otherwise a **public static `getInstance()`**; otherwise it throws `FailedToAutoWire`. It
reads `#[AutoWire('serviceName')]` attributes on that method — one per positional argument, in
declaration order — and pulls each argument from the container:

```php
use orange\framework\attributes\AutoWire;

class MyService
{
    #[AutoWire('pdo')]
    #[AutoWire('log')]
    public function __construct(protected PDO $pdo, protected LogInterface $log) { /* ... */ }
}
```

Registered as `'^myService' => MyService::class`, this resolves `pdo` and `log` from the container
and passes them, in order, to the constructor.

> **Closures vs auto‑wiring.** A closure is explicit and usually clearer — you can see exactly what
> gets passed. Auto‑wiring is convenient for classes with many container‑sourced dependencies.
> Most framework services use `getInstance(...)` closures; the example app registers its
> `RecordModel` with a closure too (below). Reach for `^` + `#[AutoWire]` when it genuinely reduces
> boilerplate.

## Singletons: build once, share

Orange's base classes distinguish **factories** (a fresh instance per `getInstance()`) from
**singletons** (one cached instance). When a closure or auto‑wired class returns an object that
extends `Singleton` or `SingletonArrayObject`, the container automatically **promotes it to a
stored value** — so subsequent `get()` calls return the same instance without re‑running the
closure. All the core services (`Config`, `Router`, `Output`, …) are singletons, so
`container()->router` always returns the one router.

If you need a fresh instance every time, base your class on `Factory` instead; the container won't
cache it.

## Registering your own services

Add app services to `config/services.php`. Here is the example app's file, which registers a
`pdo` connection and a `RecordModel`:

```php
// config/services.php
use api\models\RecordModel;
use orange\framework\interfaces\ContainerInterface;

return [
    'pdo' => function () {
        $env = env('db');
        $dsn = "mysql:host={$env['host']};port={$env['port']};dbname={$env['database']};charset={$env['charset']}";
        return new PDO($dsn, $env['username'], $env['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    },

    'RecordModel' => fn(ContainerInterface $container): RecordModel
        => RecordModel::getInstance($container->pdo),
];
```

Now any controller can pull the model in with the attribute:

```php
#[AttachService('RecordModel')]
protected RecordModel $recordModel;
```

Notice how `RecordModel` depends on `pdo`, and `pdo` is only built the first time some request
actually needs the database. A request that never touches `RecordModel` never connects.

## Working with the container directly

`ContainerInterface` also offers:

```php
container()->has('pdo');        // is a service registered? (alias: isset())
container()->set('foo', $bar);  // register at runtime
container()->unset('foo');      // remove (alias: remove())
container()->debugInfo();       // ['router' => 'closure', '$mimes' => 'array', ...]
```

The container registers **itself** as the `container` service, so a closure can always reach it.

## Swapping an implementation

Because core services are typed against interfaces, you can replace one wholesale — register a
different closure under the same name returning an object that implements the same interface, and
everything that depends on it keeps working. This is how you'd, say, point `output` at a testing
double, or use a different database connection service on production via an alias.

## Summary

- One container, built from `services.php`, reachable via `container()`.
- Register services as **closures** (lazy, get the container), **values/objects**, **aliases**
  (`@`), or **auto‑wired classes** (`^` + `#[AutoWire]`).
- Objects extending `Singleton`/`SingletonArrayObject` are cached automatically.
- Add your own services to `config/services.php`; inject them with `#[AttachService]`.

---

[← Configuration](07-configuration.md) · [Manual index](README.md) · [Next: Request & response →](09-request-and-response.md)
