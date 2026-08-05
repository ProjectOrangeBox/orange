<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * The service locator every other service is reached through.
 *
 * A service is registered under a name and resolved on first read, not on
 * registration - so the container holds recipes until something asks. What a
 * name may be bound to is deliberately broad: a closure that builds the value,
 * an alias pointing at another name, an already-built object, a plain value, or
 * a class name to autowire.
 *
 * Each accessor is published in two spellings, magic and explicit, and they are
 * the same operation - the magic form exists so the container reads as an
 * object of services rather than a bag:
 *
 *   $container->router        $container->get('router')
 *   isset($container->router) $container->isset('router') / has('router')
 *   unset($container->router) $container->unset('router') / remove('router')
 *
 * Two resolution rules are worth knowing because they are invisible at the call
 * site. A closure is invoked once and its result cached, so every read after
 * the first is the same instance. And a service that extends the framework's
 * Singleton base is converted to a stored value the first time it resolves,
 * which is what makes Singleton::getInstance() and a container read agree
 * instead of racing to build two copies.
 *
 * The constants below are two different things sharing one list, which is worth
 * reading twice. CLOSURE, ALIAS, VALUE, OBJECT and AUTOWIRECLASS are type tags -
 * what a registration *is*. TYPE and REFERENCE are array indexes into a
 * registration row - where the tag and the payload *sit*. TYPE is never itself
 * a value of the TYPE slot.
 */
interface ContainerInterface
{
    public const CLOSURE = 1;
    public const ALIAS = 2;
    public const VALUE = 3;
    public const OBJECT = 4;
    public const TYPE = 5;
    public const REFERENCE = 6;
    public const AUTOWIRECLASS = 7;

    public function __get(string $serviceName): mixed;
    public function get(string $serviceName): mixed;

    /**
     * @param mixed $arg
     */
    public function __set(string $serviceName, $arg): void;
    public function set(string $serviceName, mixed $arg = null): void;

    public function __isset(string $serviceName): bool;
    public function isset(string $serviceName): bool;
    public function has(string $serviceName): bool;

    public function __unset(string $serviceName): void;
    public function unset(string $serviceName): void;
    public function remove(string $serviceName): void;

    public function __debugInfo(): array;
    /**
     * @return array<string, mixed>
     */
    public function debugInfo(): array;
}
