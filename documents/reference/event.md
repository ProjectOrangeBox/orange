# Reference: Event

[← Reference index](README.md) · Guide: [Events](../10-events.md)

`orange\framework\Event implements EventInterface` — a priority‑ordered publish/subscribe bus.
Singleton, registered as `events` and aliased `event`. Reach it via `container()->events`.

## Priority constants

```php
EventInterface::PRIORITY_LOWEST   // 10
EventInterface::PRIORITY_LOW      // 20
EventInterface::PRIORITY_NORMAL   // 50  (default)
EventInterface::PRIORITY_HIGH     // 80
EventInterface::PRIORITY_HIGHEST  // 90
```

Listeners run **highest first**.

## Methods

### `register(string $trigger, Closure|array $callable, int $priority = self::PRIORITY_NORMAL): int`

Register a listener (a closure or a `[Class::class, 'method']` pair) for a trigger. Returns an
integer id for later `unregister()`.

```php
$id = $events->register('user.registered', [Mailer::class, 'sendWelcome'], EventInterface::PRIORITY_HIGH);
```

### `registerMultiple(array $multiple, int $priority = self::PRIORITY_NORMAL): array`

Register several listeners at once; returns their ids.

### `trigger(string $trigger, &...$arguments): self`

Fire a trigger, running its listeners highest‑priority‑first. Arguments pass **by reference**, so
listeners can mutate them. A listener returning `false` halts the remaining listeners.

```php
$events->trigger('before.output', $router, $input, $output);
```

### `has(string $trigger): bool`

Whether any listener is registered for a trigger.

### `triggers(): array`

All registered trigger names.

### `unregister(int $eventId): bool`

Remove one listener by the id `register()` returned.

### `unregisterAll(?string $trigger = null): bool`

Remove all listeners for a trigger, or (with no argument) every listener.

## Lifecycle triggers fired by the framework

| Trigger | Arguments |
|---------|-----------|
| `before.router` | `input` |
| `before.controller` | `router`, `input` |
| `before.output` | `router`, `input`, `output` |
| `before.shutdown` | `router`, `input`, `output` |

Register listeners for these in `config/event.php`. See the [guide](../10-events.md).

## Enabling / disabling

The bus can be switched off globally and back on (used in testing) via `disable()`/`enable()`.
While disabled, `trigger()` runs no listeners.

---

[← Reference index](README.md) · Guide: [Events](../10-events.md)
