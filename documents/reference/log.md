# Reference: Log

[← Reference index](README.md) · Guide: [Logging & error handling](../11-logging-and-errors.md)

`orange\framework\Log implements LogInterface` and PSR‑3's `Psr\Log\LoggerInterface`. A configurable
threshold (bitmask) decides which levels are written; messages are written to a file or forwarded
to an injected PSR‑3 handler. Singleton. Reach it via `container()->log` or the `logMsg()` helper.

## Level constants (bitmask)

```php
LogInterface::NONE       // 0
LogInterface::EMERGENCY  // 1
LogInterface::ALERT      // 2
LogInterface::CRITICAL   // 4
LogInterface::ERROR      // 8
LogInterface::WARNING    // 16
LogInterface::NOTICE     // 32
LogInterface::INFO       // 64
LogInterface::DEBUG      // 128
LogInterface::ALL        // 255
```

Combine with `|` to enable a set of levels: `ERROR | WARNING`.

## Threshold

### `changeThreshold(int $threshold): self`

Set which levels are active.

### `getThreshold(): int`

The current threshold bitmask.

### `isEnabled(): bool`

Whether logging is on at all (threshold ≠ `NONE`).

### `isLevelEnabled(string|int $level): bool`

Whether a specific level would be written. Memoised per level. The global `isLogEnabled()` helper
delegates here — use it to guard costly message construction.

## Writing

### `write(string|int $level, string|Stringable $message, array $context = []): void`

Write a message at a level (name or bit). No‑ops if the level isn't enabled.

### `log($level, string|Stringable $message, array $context = []): void`

PSR‑3 generic log method (what `logMsg()` calls).

### PSR‑3 level shortcuts

```php
$log->emergency($message, $context);
$log->alert($message, $context);
$log->critical($message, $context);
$log->error($message, $context);
$log->warning($message, $context);
$log->notice($message, $context);
$log->info($message, $context);
$log->debug($message, $context);
```

## Configuration (`log` config)

| Key | Default | Purpose |
|-----|---------|---------|
| `threshold` | `Log::NONE` | Enabled levels (bitmask) |
| `filepath` | `__ROOT__/var/logs/{Y-m-d}.log` | Output file |
| `permissions` | `0644` | File mode |
| `line format` | `%timestamp %level %message %context\n` | Line template |
| `timestamp format` | `Y-m-d H:i:s` | `%timestamp` format |

The logger creates the log directory and applies permissions on first write. To forward messages
elsewhere, inject a PSR‑3 handler for the logger to delegate to.

---

[← Reference index](README.md) · Guide: [Logging & error handling](../11-logging-and-errors.md)
