<?php

declare(strict_types=1);

namespace orange\framework;

use orange\framework\interfaces\DataInterface;
use orange\framework\base\SingletonArrayObject;

/**
 * Overview of Data.php
 *
 * This file defines the Data class in the orange\framework namespace.
 * It acts as the framework’s centralized data container, designed to hold
 * and manage application-level data in a structured way.
 *
 * ⸻
 *
 * 1. Core Purpose
 *  •   Implements the DataInterface, which defines the expected contract for a data service in the framework.
 *  •   Extends SingletonArrayObject, making it both:
 *  •   A singleton → only one instance exists during runtime.
 *  •   An enhanced array-like object → allows property-style access ($data->key) as well as array-style access ($data['key']).
 *
 * This makes it the shared data store available across the application lifecycle.
 *
 * @package orange\framework
 */
class Data extends SingletonArrayObject implements DataInterface
{
    /**
     * is not allowed to call from outside to prevent from creating multiple instances,
     * to use the singleton, you have to obtain the instance from Singleton::getInstance() instead
     */
    protected function __construct(array $data = [])
    {
        logMsg('INFO', __METHOD__);

        // SingletonArrayObject's inherited constructor (base\ArrayObject::__construct)
        // takes only $input and already hardcodes ARRAY_AS_PROPS itself - a second
        // argument here would silently be discarded
        parent::__construct($this->buildArrayObjects($data));
    }
}
