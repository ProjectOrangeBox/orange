<?php

declare(strict_types=1);

namespace orange\framework\base;

use orange\framework\base\ArrayObject;
use orange\framework\base\traits\BaseTraits;
use orange\framework\base\traits\SingletonTraits;

class SingletonArrayObject extends ArrayObject
{
    use BaseTraits;
    use SingletonTraits;

    // BaseTraits also declares a no-arg __construct(); without redeclaring it
    // here, that trait copy would win over the inherited ArrayObject::__construct(),
    // silently discarding whatever $input subclasses (e.g. Data) pass to
    // parent::__construct().
    protected function __construct(array $input = [])
    {
        parent::__construct($input);
    }
}
