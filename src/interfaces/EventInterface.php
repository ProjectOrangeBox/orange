<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

interface EventInterface
{
    public const PRIORITY_LOWEST = 10;
    public const PRIORITY_LOW = 20;
    public const PRIORITY_NORMAL = 50;
    public const PRIORITY_HIGH = 80;
    public const PRIORITY_HIGHEST = 90;

    /**
     * @param \Closure|array{0: object|string, 1: string} $callable
     */
    public function register(string $trigger, \Closure|array $callable, int $priority = self::PRIORITY_NORMAL): int;
    /**
     * @param array<array-key, mixed> $multiple
     * @return array<array-key, int> the registration id of each listener added
     */
    public function registerMultiple(array $multiple, int $priority = self::PRIORITY_NORMAL): array;
    /**
     * @param mixed ...$arguments
     */
    public function trigger(string $trigger, &...$arguments): self;
    public function has(string $trigger): bool;
    /**
     * @return list<string> every registered trigger name
     */
    public function triggers(): array;
    public function unregister(int $eventId): bool;
    public function unregisterAll(?string $trigger = null): bool;
}
