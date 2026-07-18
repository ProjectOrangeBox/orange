<?php

declare(strict_types=1);

namespace orange\framework\exceptions;

use Throwable;
use orange\framework\Error;

// "parent" of all orange exceptions

class OrangeException extends \Exception
{
    public string $namespacedClass;
    public string $className;
    public string $classMsg;

    public function __construct($message = '', $code = 0, ?Throwable $previous = null)
    {
        /* since we must pass an array by ref into array_pop we need to put it into a variable */
        $this->namespacedClass = static::class;

        $segments = explode('\\', $this->namespacedClass);
        $this->className = array_pop($segments);

        $this->classMsg = implode(' ', preg_split('/(?=[A-Z])/', $this->className));

        parent::__construct(trim($this->classMsg . ' ' . $message), $code, $previous);
    }

    public function decorate(Error $error): void
    {
        // child classes can extend this method and
        // access the properties & methods on the error class passed in
        // to interact with it.
    }
}
