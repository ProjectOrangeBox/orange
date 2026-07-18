<?php

declare(strict_types=1);

namespace orange\framework\base\traits;

use orange\framework\exceptions\container\CannotCloneSingleton;
use orange\framework\exceptions\container\CannotUnserializeSingleton;

trait BaseTraits
{
    protected array $config;

    /**
     * singletons should not have public constructors
     * if you "MUST" get a new instance call newInstance(...)
     *
     * @return void
     */
    protected function __construct()
    {
        // placeholder
    }

    /**
     * Allow the creation of a new instance
     * This should ONLY be called if you MUST get a new instance.
     * for testing etc...
     */
    public static function newInstance(): mixed
    {
        $args = func_get_args();

        // only this method can call "new" on this class
        return new static(...$args);
    }

    /**
     * Singletons can not be cloned
     * This would defeat making a Singleton
     * ie. "single" - it's in the name
     *
     * @return never
     * @throws CannotCloneSingleton
     */
    public function __clone()
    {
        throw new CannotCloneSingleton();
    }

    /**
     * Singletons cannot be woke up
     * because you can not serialize a Singleton
     *
     * @return never
     * @throws CannotUnserializeSingleton
     */
    public function __wakeup()
    {
        throw new CannotUnserializeSingleton();
    }
}
