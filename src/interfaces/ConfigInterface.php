<?php

declare(strict_types=1);

namespace orange\framework\interfaces;

/**
 * Also exposes config files via array access - $config['app']['debug'] - as a
 * read-only alternative to get()/__get(). offsetSet()/offsetUnset() must throw:
 * implementations of this interface are immutable, loaded-once configuration,
 * not mutable state.
 */
interface ConfigInterface extends \ArrayAccess
{
    public function __get(string $filename): mixed;
    public function get(string $filenameKey, mixed $defaultValue = null): mixed;
}
