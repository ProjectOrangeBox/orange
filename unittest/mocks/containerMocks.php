<?php

declare(strict_types=1);

use orange\framework\attributes\AutoWire;

/**
 * Mocks for exercising Container autowiring paths.
 */

/* public constructor, AutoWire attribute -> resolved via newInstanceArgs() */
class autowireConstructorMock
{
    public mixed $injected;

    #[AutoWire('foo')]
    public function __construct(mixed $foo = null)
    {
        $this->injected = $foo;
    }
}

/* private constructor + public static getInstance -> resolved via getInstance().
 * AutoWire attributes go on getInstance() itself, since that's the method the
 * container actually invokes - not on the (unused) private constructor. */
class autowireGetInstanceMock
{
    public mixed $injected;

    private function __construct(mixed $foo = null)
    {
        $this->injected = $foo;
    }

    #[AutoWire('foo')]
    public static function getInstance(mixed $foo = null): self
    {
        return new self($foo);
    }
}

/* private constructor carries an AutoWire attribute but getInstance() does not -
 * regression guard: the constructor is never actually called here, so its
 * AutoWire attribute must NOT leak into getInstance()'s arguments. */
class autowireAttributeOnUnusedConstructorMock
{
    public mixed $injected;

    #[AutoWire('foo')]
    private function __construct(mixed $foo = 'construct-default')
    {
        $this->injected = $foo;
    }

    public static function getInstance(mixed $foo = 'getinstance-default'): self
    {
        return new self($foo);
    }
}

/* no public constructor and no getInstance -> FailedToAutoWire */
class autowireImpossibleMock
{
    private function __construct() {}
}

/* explicit public constructor, no autowire attributes -> constructed with no args */
class autowirePlainMock
{
    public bool $built = false;

    public function __construct()
    {
        $this->built = true;
    }
}
