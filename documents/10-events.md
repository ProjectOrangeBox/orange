# 10. Events

[← Request & response](09-request-and-response.md) · [Manual index](README.md) · [Next: Logging & error handling →](11-logging-and-errors.md)

The event service is a small publish/subscribe bus. The framework uses it for the four request
lifecycle hooks, and you can use it for your own triggers. Listeners run in priority order and can
halt the chain.

## The `events` service

`orange\framework\Event` implements `EventInterface`. It's registered as `events` and aliased
`event`. Reach it via the container or inject it:

```php
container()->events->trigger('before.shutdown');
// or
#[AttachService('events')] protected EventInterface $events;
```

## Lifecycle triggers

`Application::http()` fires these four triggers, in order, around the pipeline (see
[Chapter 3](03-request-lifecycle.md)):

| Trigger | When it fires | Listener arguments |
|---------|---------------|--------------------|
| `before.router` | Before route matching | `input` |
| `before.controller` | After a route is matched, before dispatch | `router`, `input` |
| `before.output` | After the body is buffered, before it's sent | `router`, `input`, `output` |
| `before.shutdown` | After output has been sent | `router`, `input`, `output` |

These are the framework's own hooks. Subscribe to them to run cross‑cutting logic — logging,
auth, timing, adding response headers — without touching controllers.

## Registering listeners

The common place to register listeners is `config/event.php`, which returns a map of trigger name
to a list of listeners. The bundled app ships an empty one:

```php
// config/event.php
return [
];
```

A listener is a closure or a `[Class::class, 'method']` pair. Register several triggers at once by
returning them from the config:

```php
// config/event.php
use orange\framework\interfaces\RouterInterface;
use orange\framework\interfaces\InputInterface;
use orange\framework\interfaces\OutputInterface;

return [
    'before.controller' => [
        [function (RouterInterface $router, InputInterface $input) {
            logMsg('INFO', 'Dispatch: ' . $input->requestMethod() . ' ' . $input->requestUri());
        }],
    ],
    'before.output' => [
        [function (RouterInterface $router, InputInterface $input, OutputInterface $output) {
            $output->header('X-Powered-By: Orange');
        }],
    ],
];
```

You can also register at runtime through the service:

```php
$events = container()->events;

// a closure listener
$id = $events->register('user.registered', function (User $user) {
    logMsg('INFO', 'New user: ' . $user->email);
});

// a [class, method] listener with a priority
$events->register('user.registered', [Mailer::class, 'sendWelcome'], EventInterface::PRIORITY_HIGH);
```

`register()` returns an integer id you can later pass to `unregister($id)`.

## Priorities

Listeners run **highest priority first**. Use the constants on `EventInterface`:

```php
EventInterface::PRIORITY_LOWEST   // 10
EventInterface::PRIORITY_LOW      // 20
EventInterface::PRIORITY_NORMAL   // 50  (default)
EventInterface::PRIORITY_HIGH     // 80
EventInterface::PRIORITY_HIGHEST  // 90
```

```php
$events->register('before.output', $addHeaders, EventInterface::PRIORITY_HIGH);
```

## Triggering and halting

Fire a trigger with `trigger($name, &...$args)`. Arguments are passed **by reference**, so a
listener can mutate them (this is how `before.output` listeners modify the `output` object):

```php
$events->trigger('before.output', $router, $input, $output);
```

A listener can **stop the remaining listeners** for that trigger by returning `false`:

```php
$events->register('before.controller', function ($router, $input) {
    if (!userIsAuthenticated()) {
        show403();       // throws; but returning false would also halt the chain
        return false;
    }
});
```

## Managing listeners

```php
$events->has('user.registered');        // is anything listening?
$events->triggers();                    // list of registered trigger names
$events->unregister($id);               // remove one listener by id
$events->unregisterAll('user.registered'); // remove all listeners for a trigger
$events->unregisterAll();               // remove every listener
```

The whole bus can be switched off and back on globally (used in testing) — see the
[Event reference](reference/event.md).

## Custom events

Nothing about the bus is framework‑specific. Define your own triggers to decouple parts of your
app:

```php
// somewhere in a service
container()->events->trigger('order.placed', $order);

// in config/event.php or a bootstrap
container()->events->register('order.placed', [Invoicer::class, 'generate']);
container()->events->register('order.placed', [Notifier::class, 'email'], EventInterface::PRIORITY_LOW);
```

## Summary

- The `events` service (alias `event`) is a priority‑ordered pub/sub bus.
- The framework fires `before.router`, `before.controller`, `before.output`, `before.shutdown`.
- Register listeners in `config/event.php` or at runtime; they run highest‑priority‑first.
- Arguments pass by reference; returning `false` halts the chain.

---

[← Request & response](09-request-and-response.md) · [Manual index](README.md) · [Next: Logging & error handling →](11-logging-and-errors.md)
