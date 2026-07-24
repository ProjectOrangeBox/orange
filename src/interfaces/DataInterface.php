<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

use ArrayAccess;

/**
 * Contract for the framework's shared data container.
 *
 * The concrete service (orange\framework\Data) is an ArrayObject built with
 * ARRAY_AS_PROPS, so callers reach values two equivalent ways and both are
 * part of the published contract:
 *
 *   $data['name'] = 'Johnny';   // offset access
 *   $data->name   = 'Johnny';   // property access, via __get()/__set()
 *
 * Declaring only merge() here left every one of those accesses untyped for
 * callers holding a DataInterface, so this interface declares the array
 * access and the magic accessors the container actually provides.
 *
 * @extends ArrayAccess<string, mixed>
 */
interface DataInterface extends ArrayAccess
{
    /**
     * Read a value by name; missing names return null rather than raising.
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed;

    /**
     * Write a value by name.
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void;

    /**
     * Whether a value is set under $name.
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool;

    /**
     * Read a value by name, falling back to $default when it isn't set.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, mixed $default): mixed;

    /**
     * Merge an array into the container.
     *
     * @param array $array
     * @param bool $recursive
     * @param bool $replace
     * @return static
     */
    public function merge(array $array, bool $recursive = true, bool $replace = true): static;
}
