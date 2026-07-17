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

/* private constructor + public static getInstance -> resolved via getInstance() */
class autowireGetInstanceMock
{
    public mixed $injected;

    #[AutoWire('foo')]
    private function __construct(mixed $foo = null)
    {
        $this->injected = $foo;
    }

    public static function getInstance(mixed $foo = null): self
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
