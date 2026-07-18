<?php

declare(strict_types=1);

namespace orange\framework\base\traits;

trait FactoryTraits
{
    /**
     * The method you use to generate a new instance of the class.
     *
     * Unlike SingletonTraits::getInstance(), this does NOT cache the
     * result - every call to newInstance() (via this method) returns a
     * brand new instance.
     */
    public static function getInstance(): mixed
    {
        $args = func_get_args();

        return static::newInstance(...$args);
    }
}
