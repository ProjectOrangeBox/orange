# Reference: Container

[← Reference index](README.md) · Guide: [Dependency injection](../08-dependency-injection.md)

`orange\framework\Container implements ContainerInterface` — the dependency‑injection container. A
singleton, built during bootstrap from `services.php`, and registered as the `container` service
so it can inject itself. Reach it via the `container()` helper.

## Constants (service types)

`ContainerInterface` exposes internal type tags; you don't normally use them, but they document
the registration styles:

```php
ContainerInterface::CLOSURE        // 1
ContainerInterface::ALIAS          // 2
ContainerInterface::VALUE          // 3
ContainerInterface::OBJECT         // 4
ContainerInterface::TYPE           // 5  (array-key index)
ContainerInterface::REFERENCE      // 6  (array-key index)
ContainerInterface::AUTOWIRECLASS  // 7
```

## Retrieving services

### `get(string $serviceName): mixed` / `__get(string $serviceName): mixed`

Resolve and return a service. Property access (`$container->router`) and method access
(`$container->get('router')`) are equivalent. Closures are invoked (and their object results
promoted to singletons where applicable); auto‑wire classes are reflected and built; values/objects
are returned as‑is.

- **Throws** `ServiceNotFound` if unregistered; `InvalidValue` if an alias chain exceeds depth 16;
  `FailedToAutoWire` if an auto‑wire class can't be built.

```php
$router = container()->router;
$pdo    = container()->get('pdo');
```

## Registering services

### `set(string $serviceName, mixed $arg = null): void` / `__set(string $serviceName, $arg): void`

Register a service. The behavior depends on the name prefix and value:

| Input | Registered as |
|-------|---------------|
| `set('@alias', 'target')` | Alias to `target` |
| `set('^name', Class::class)` | Auto‑wire class |
| `set('name', fn($c) => …)` | Closure (lazy) |
| `set('name', $value)` | Value / object |

```php
container()->set('clock', fn() => new App\Clock());
container()->set('@response', 'output');            // alias
container()->set('^mailer', App\Mailer::class);     // auto-wired
container()->appName = 'My App';                     // value via __set
```

## Inspecting & removing

### `has(string $serviceName): bool` / `isset(string $serviceName): bool` / `__isset(...)`

Whether a service is registered. `has()` and `isset()` are equivalent.

### `unset(string $serviceName): void` / `remove(string $serviceName): void` / `__unset(...)`

Remove a service. `remove()` and `unset()` are equivalent.

### `getServices(): array`

All registered service names.

### `debugInfo(): array` / `__debugInfo(): array`

Map of service name → type label (`'closure'`, `'object'`, `'alias'`, `'autowired fully qualifed
classname'`, or the gettype of a value).

```php
var_dump(container());       // uses __debugInfo(): ['router' => 'closure', '$mimes' => 'array', ...]
```

## Auto-wiring

When resolving a service registered with the `^` prefix, the container reflects on the class:

1. Uses the **public constructor** if present; else a **public static `getInstance()`**; else
   throws `FailedToAutoWire`.
2. Reads `#[AutoWire('serviceName')]` attributes on that method — one per positional argument, in
   declaration order — and resolves each from the container.
3. Promotes the result to a singleton if it extends `Singleton`/`SingletonArrayObject`.

See [`#[AutoWire]`](attributes.md#autowire).

## Singleton promotion

Any object returned by a closure or auto‑wire resolution that extends
`orange\framework\base\Singleton` or `SingletonArrayObject` is stored as a value, so later `get()`
calls return the same instance without re‑running the closure. Base a class on `Factory` instead
to get a fresh instance each time.

## Exceptions

| Exception | When |
|-----------|------|
| `exceptions\container\ServiceNotFound` | Service name not registered |
| `exceptions\InvalidValue` | Alias resolution exceeded depth 16 |
| `exceptions\container\FailedToAutoWire` | No usable constructor / `getInstance()` |
| `exceptions\NotFound` | Unknown service type in `debugInfo()` |

---

[← Reference index](README.md) · Guide: [Dependency injection](../08-dependency-injection.md)
