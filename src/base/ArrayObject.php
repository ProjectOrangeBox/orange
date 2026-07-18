<?php

declare(strict_types=1);

namespace orange\framework\base;

use ArrayObject as PHPArrayObject;
use orange\framework\base\traits\BaseTraits;
use orange\framework\base\traits\FactoryTraits;
use orange\framework\exceptions\MagicMethodNotFound;

class ArrayObject extends PHPArrayObject
{
    use BaseTraits;
    use FactoryTraits;

    protected function __construct(array $input = [])
    {
        parent::__construct($input, PHPArrayObject::ARRAY_AS_PROPS);
    }

    /**
     * add wrapper for new  error when dynamically assigning property
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this[$name] = $value;
    }

    /**
     * let "some" of the array_ functions work
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws MagicMethodNotFound
     */
    public function __call(string $name, array $arguments)
    {
        if (!is_callable($name) || !str_starts_with($name, 'array_')) {
            throw new MagicMethodNotFound(self::class . '->' . $name);
        }

        return call_user_func_array($name, array_merge([$this->getArrayCopy()], $arguments));
    }

    /**
     * check if key exists
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        return isset($this[$name]);
    }

    /**
     * wrapper to treat like an object
     *
     * @param string $name
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this[$name] ?? null;
    }

    /**
     * get with default
     * Returns the value at the specified key if it exists; otherwise, returns the provided default value.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function get(string $name, mixed $default): mixed
    {
        return $this[$name] ?? $default;
    }

    /**
     * Allow ArrayObject "merging"
     *
     * @param array $array
     * @param bool $recursive
     * @param bool $replace
     * @return static
     */
    public function merge(array $array, bool $recursive = true, bool $replace = true): static
    {
        // convert ArrayObject into an array
        $currentArray = (array)$this;

        // more than likely you want to replace what is already in data not merge with it
        if ($replace) {
            $data = ($recursive) ? array_replace_recursive($currentArray, $array) : array_replace($currentArray, $array);
        } else {
            $data = ($recursive) ? array_merge_recursive($currentArray, $array) : array_merge($currentArray, $array);
        }

        // swap
        $this->exchangeArray($this->buildArrayObjects($data));

        return $this;
    }

    /**
     * Shallow-copies the given array for exchangeArray()/the constructor.
     *
     * NOTE: despite the name, this does NOT recursively wrap nested arrays into
     * ArrayObject instances - nested values are kept as plain arrays on purpose, so
     * merge() can keep using array_replace_recursive()/array_merge_recursive() on
     * them (those only recurse into plain arrays, not ArrayObject instances).
     *
     * @param array $data
     * @return array
     */
    protected function buildArrayObjects(array $data)
    {
        $array = [];

        foreach ($data as $key => $value) {
            $array[$key] = $value;
        }

        return $array;
    }
}
