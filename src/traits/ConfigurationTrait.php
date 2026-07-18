<?php

declare(strict_types=1);

namespace orange\framework\traits;

use orange\framework\exceptions\InvalidValue;
use orange\framework\exceptions\MissingRequired;
use orange\framework\exceptions\config\ConfigFileNotFound;

trait ConfigurationTrait
{
    private static array $alreadyIncludedFiles = [];
    private static array $configPathFilenameCache = [];

    /**
     * This allows us to call $object->changeOption('fooBar', 123) on the class which will
     * set the value $this->fooBar = 123; with type checking
     *
     * $this->changeableTypeCheck['fooBar'=>'is_integer'];
     *
     * @param string $name
     * @param mixed $value
     * @return self
     * @throws MissingRequired
     * @throws InvalidValue
     */
    public function changeOption(string $name, mixed $value): self
    {
        logMsg('INFO', __METHOD__ . ' ' . $name);
        logMsg('DEBUG', __METHOD__, ['name' => $name, 'value' => $value]);

        if (!property_exists($this, 'changeableTypeCheck')) {
            throw new MissingRequired('Change not supported');
        }

        if (!is_array($this->changeableTypeCheck)) {
            throw new InvalidValue('changeableTypeCheck is not an array.');
        }

        // convert a human readable name to a variable name
        // convert 'Shipping Carrier' to 'shippingCarrier'
        $name = $this->camelize($name, false);

        if (!isset($this->changeableTypeCheck[$name])) {
            throw new InvalidValue('Cannot set ' . $name);
        }

        $typeCheck = $this->changeableTypeCheck[$name];

        $isValid = function_exists($typeCheck) ? $typeCheck($value) : $value instanceof $typeCheck;

        if (!$isValid) {
            // objects and arrays are not stringable so describe them by type
            throw new InvalidValue((is_scalar($value) ? (string)$value : get_debug_type($value)) . ' is not ' . $typeCheck);
        }

        $method = 'set' . $this->camelize($name, true);

        // only call if the method exists
        if (method_exists($this, $method)) {
            $this->$method($value);
        } elseif (property_exists($this, $name)) {
            // set value
            $this->$name = $value;
        } else {
            throw new InvalidValue('property or set method not found ' . $name);
        }

        return $this;
    }

    /**
     * Merge the passed config array with the default configuration in the file provided by absolute path
     * if the absolute path to the file does not exsist try to auto detect based on the file location + /config/{name}.php
     * optionally doing a recursive merge
     *
     * @param array $config
     * @param string|bool|null $path Absolute path to the config file, or a bool to be
     *        reinterpreted as $recursive when the caller omits $path (see is_bool() below).
     * @param bool $recursive
     * @return array
     * @throws ConfigFileNotFound
     * @throws InvalidValue
     */
    protected function mergeConfigWith(array $config, mixed $path = null, bool $recursive = true): array
    {
        logMsg('INFO', __METHOD__);
        logMsg('DEBUG', __METHOD__, ['config' => $config, 'path' => $path, 'recursive' => $recursive]);

        // if they send in $recursive as $path
        if (is_bool($path)) {
            $recursive = $path;
            $path = null;
        }

        if (empty($path) || !file_exists($path)) {
            $path = $this->determineConfigPath($path);
        }

        logMsg('INFO', $path . ' ' . ($recursive ? 'recursive' : 'non-recursive'));

        if (!isset(self::$alreadyIncludedFiles[$path])) {
            logMsg('INFO', 'INCLUDE FILE "' . $path . '"');

            self::$alreadyIncludedFiles[$path] = include $path;

            if (!is_array(self::$alreadyIncludedFiles[$path])) {
                throw new InvalidValue('"' . $path . '" did not return an array.');
            }
        }

        return ($recursive) ? array_replace_recursive(self::$alreadyIncludedFiles[$path], $config) : array_replace(self::$alreadyIncludedFiles[$path], $config);
    }

    /**
     * Wrapper for mergeConfigWith with slightly different signature
     *
     * @param string|null $path
     * @param array $configArray
     * @param bool $recursive
     * @return array
     * @throws ConfigFileNotFound
     * @throws InvalidValue
     */
    protected function getConfigFile(?string $path = null, array $configArray = [], bool $recursive = true): array
    {
        return $this->mergeConfigWith($configArray, $path, $recursive);
    }

    protected function determineConfigPath(?string $arg): string
    {
        $class = static::class;

        // cache the reflection lookup per class: the class's own file path
        // never changes for the life of the process, so avoid rebuilding a
        // ReflectionClass (and re-deriving the short name) on every call
        if (!isset(self::$configPathFilenameCache[$class])) {
            $reflection = new \ReflectionClass($class);

            self::$configPathFilenameCache[$class] = [$reflection->getFileName(), mb_strtolower($reflection->getShortName())];
        }

        [$filename, $defaultShortName] = self::$configPathFilenameCache[$class];
        $shortName = empty($arg) ? $defaultShortName : $arg;
        $dir = dirname((string) $filename);

        $path = $dir . '/config/' . $shortName . '.php';

        if (file_exists($path)) {
            return $path;
        }

        $path = $dir . '/../config/' . $shortName . '.php';

        if (file_exists($path)) {
            return $path;
        }

        throw new ConfigFileNotFound($filename . '~' . $shortName);
    }

    /**
     * set values passed as key, values pairs in config
     * into setter methods with names matching the camelized key
     * prefixed with set
     *
     * $config['foo bar'] = 123;
     * would call $this->setFooBar(123);
     *
     * @param array $config
     * @param bool $throwException
     * @return void
     * @throws InvalidValue
     */
    protected function setFromConfig(array $config, bool $throwException = false): void
    {
        logMsg('INFO', __METHOD__);
        logMsg('DEBUG', __METHOD__, $config);

        foreach ($config as $name => $value) {
            // a config key of "default merge data"
            // would call setDefaultMergeData()
            $method = 'set' . $this->camelize($name, true);

            // only call if the method exists
            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                if ($throwException) {
                    throw new InvalidValue('method not found ' . $method . '.');
                }
            }
        }
    }

    /**
     * set values passed as key, values pairs in config
     * into properties with names matching the camelized key
     *
     * $config['foo bar'] = 123;
     * would set $this->fooBar = 123;

     * @param array $config
     * @param bool $throwException
     * @return void
     * @throws InvalidValue
     */
    protected function assignFromConfig(array $config, bool $throwException = false): void
    {
        logMsg('INFO', __METHOD__);
        logMsg('DEBUG', __METHOD__, $config);

        foreach ($config as $name => $value) {
            $name = $this->camelize($name, false);

            if (property_exists($this, $name)) {
                $this->$name = $value;
            } else {
                if ($throwException) {
                    throw new InvalidValue('property not found ' . $name . '.');
                }
            }
        }
    }

    /**
     * Normalize the string
     *
     * @param string $str
     * @return string
     */
    protected function normalize(string $str): string
    {
        return mb_convert_case($str, MB_CASE_LOWER, mb_detect_encoding($str));
    }

    /**
     * Camelize
     *
     * Takes multiple words separated by spaces or underscores and camelizes them
     *
     * @param string $str
     * @param bool $ucFirst
     * @return string
     */
    protected function camelize(string $str, bool $ucFirst = false)
    {
        $converted = mb_strtolower($str[0]) . substr(str_replace(' ', '', ucwords((string) preg_replace('/[\s_]+/', ' ', $str))), 1);

        return $ucFirst ? ucfirst($converted) : $converted;
    }

    /**
     * Underscore
     *
     * Takes multiple words separated by spaces and underscores them
     *
     * @param string $str
     * @return string|string[]|null
     */
    protected function underscore(string $str)
    {
        return preg_replace('/[\s]+/', '_', mb_strtolower($str));
    }

    /**
     * Humanize
     *
     * Takes multiple words separated by the separator and changes them to spaces
     *
     * @param string $str
     * @param string $separator
     * @return string
     */
    protected function humanize(string $str, string $separator = '_')
    {
        return ucwords((string) preg_replace('/[' . preg_quote($separator, '/') . ']+/', ' ', mb_strtolower($str)));
    }

    /**
     * simple validation for variables
     *
     * config [
     *
     * ]
     *
     * @param array $config
     * @param array $rules
     * @return void
     * @throws InvalidValue
     */
    protected function validateConfig(array $config, array $rules): void
    {
        logMsg('INFO', __METHOD__);
        logMsg('DEBUG', __METHOD__, ['config' => $config, 'rules' => $rules]);

        $errors = [];

        foreach ($config as $key => $value) {
            if (isset($rules[$key])) {
                // type never changes across rules for this key, so compute it once
                $type = gettype($value);

                // in this case we bail on the first on a giving key
                foreach (explode(',', $rules[$key]) as $rule) {
                    $hasOption = strpos($rule, '[');
                    $option = null;

                    if ($hasOption !== false) {
                        $option = substr($rule, $hasOption + 1, -1);
                        $rule = substr($rule, 0, $hasOption);
                    }

                    // these rules can't be evaluated without a bracketed option, e.g. "min[5]"
                    if ($option === null && in_array($rule, ['min', 'max', 'count', 'size', 'class'], true)) {
                        throw new InvalidValue('Rule "' . $rule . '" requires an option, e.g. "' . $rule . '[value]".');
                    }

                    switch ($rule) {
                        case 'object':
                        case 'bool':
                        case 'integer':
                        case 'int':
                        case 'float':
                        case 'double':
                        case 'string':
                        case 'array':
                        case 'resource':
                            // convert int to integer
                            $rule = ($rule == 'int') ? 'integer' : $rule;

                            if ($type != $rule) {
                                $errors[] = $key . ' not an ' . $rule;
                            }
                            break;
                        case 'min':
                            $err = ' min is not ';
                            switch ($type) {
                                case 'string':
                                    if (strlen((string) $value) < $option) {
                                        $errors[] = $key . $err . $option;
                                    }
                                    break;
                                case 'integer':
                                    if ($value < $option) {
                                        $errors[] = $key . $err . $option;
                                    }
                                    break;
                                case 'array':
                                    if (count($value) < $option) {
                                        $errors[] = $key . $err . $option;
                                    }
                                    break;
                                default:
                                    $errors[] = 'can not use min on ' . $type;
                            }
                            break;
                        case 'max':
                            $err = ' max is not ';
                            switch ($type) {
                                case 'string':
                                    if (strlen((string) $value) > $option) {
                                        $errors[] = $key . $err . $option;
                                    }
                                    break;
                                case 'integer':
                                    if ($value > $option) {
                                        $errors[] = $key . $err . $option;
                                    }
                                    break;
                                case 'array':
                                    if (count($value) > $option) {
                                        $errors[] = $key . $err . $option;
                                    }
                                    break;
                                default:
                                    $errors[] = 'can not use max on ' . $type;
                            }
                            break;
                        case 'count':
                            if ($type !== 'array') {
                                $errors[] = 'can not use count on ' . $type;
                            } elseif (count($value) != $option) {
                                $errors[] = $key . ' count is not ' . $option;
                            }
                            break;
                        case 'size':
                            switch ($type) {
                                case 'array':
                                    if (count($value) != $option) {
                                        $errors[] = $key . ' size does not match ' . $option;
                                    }
                                    break;
                                case 'string':
                                    if (strlen((string) $value) != $option) {
                                        $errors[] = $key . ' size does not match ' . $option;
                                    }
                                    break;
                                default:
                                    $errors[] = 'can not use size on ' . $type;
                            }
                            break;
                        case 'class':
                            if (!$value instanceof $option) {
                                $errors[] = $key . ' is not an instance of ' . $option;
                            }
                            break;
                        default:
                            throw new InvalidValue('Unknown validate config rule ' . $rule);
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new InvalidValue('The following configuration key value pairs have errors ' . implode(', ', $errors) . '.');
        }
    }
}
