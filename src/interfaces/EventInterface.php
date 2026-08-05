<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * Named hook points listeners attach to.
 *
 * The framework fires a handful of these around the request pipeline -
 * before.router, before.controller, before.output, before.shutdown - and an
 * application registers against them in its event config rather than editing
 * the pipeline. Anything may define and fire its own; a trigger is just a name.
 *
 * Two things about trigger() are load-bearing and easy to miss. Its arguments
 * are taken by reference, so a listener can rewrite the payload and the change
 * is what later listeners and the caller see - this is the mechanism, not a
 * side effect, and it is how before.output listeners amend a response. And a
 * listener returning exactly false stops the chain: remaining listeners do not
 * run. Any other return value, null included, continues.
 *
 * Priority orders listeners, highest first. Listeners registered at equal
 * priority run in reverse registration order - the most recently added goes
 * first - so priority is the only ordering worth relying on. The order is
 * established once at register() time rather than on each trigger, since a
 * trigger may fire many times per request.
 *
 * register() returns an id, which is the only handle unregister() accepts -
 * keep it if a listener is ever meant to be removed individually, otherwise
 * unregisterAll() clears a whole trigger.
 */
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
