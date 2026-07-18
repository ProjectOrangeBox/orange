<?php

declare(strict_types=1);

namespace orange\framework\property;

class RouterCallback
{
    public function __construct(
        public string $controller,
        public string $method,
        public array $arguments
    ) {
    }
}
