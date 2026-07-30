<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

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
