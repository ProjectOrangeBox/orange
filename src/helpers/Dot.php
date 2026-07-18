<?php

declare(strict_types=1);

namespace orange\framework\helpers;

use orange\framework\exceptions\InvalidValue;

/**
 * Work with arrays using dot notation
 *
 * These are all static functions
 */

class Dot
{
    protected static string $delimiter = '.';

    /**
     * Changes the delimiter used for dot notation.
     *
     * @param string $delimiter The new delimiter to use.
     * @return void
     */
    public static function changeDelimiter(string $delimiter): void
    {
        static::$delimiter = $delimiter;
    }

    /**
     * Sets a value in an array or object using dot notation.
     *
     * @param array|\StdClass &$data The data structure to modify.
     * @param string $key The dot-notated key.
     * @param mixed $value The value to set.
     * @return void
     */
    public static function set(array|\StdClass &$data, string $key, mixed $value): void
    {
        // Check if the key contains the delimiter; if not, treat as simple key
        if (!str_contains($key, static::$delimiter)) {
            if (is_object($data)) {
                $data->$key = $value;
            } else {
                $data[$key] = $value;
            }
        } else {
            // Split the key into parts
            $keys = explode(static::$delimiter, $key);

            // Use a separate reference to navigate without changing $data's reference
            $current = &$data;
            foreach ($keys as $k) {
                if (is_object($current)) {
                    if (!isset($current->$k)) {
                        $current->$k = new \StdClass();
                    }
                    $current = &$current->$k;
                } else {
                    if (!isset($current[$k])) {
                        $current[$k] = [];
                    }
                    $current = &$current[$k];
                }
            }
            // Set the value at the final reference
            $current = $value;
        }
    }

    /**
     * Gets a value from an array or object using dot notation.
     *
     * @param array|\StdClass $data The data structure to access.
     * @param string $key The dot-notated key.
     * @param mixed $default The default value if key not found.
     * @return mixed The value or default.
     */
    public static function get(array|\StdClass $data, string $key, mixed $default = null): mixed
    {
        // Check if the key is simple (no delimiter)
        if (!str_contains($key, static::$delimiter)) {
            if (is_object($data)) {
                if (isset($data->$key)) {
                    $data = $data->$key;
                } else {
                    return $default;
                }
            } else {
                if (isset($data[$key])) {
                    $data = $data[$key];
                } else {
                    return $default;
                }
            }
        } else {
            // Split the key into parts
            $keys = explode(static::$delimiter, $key);

            // Traverse each key part, updating $data to the nested value
            // (named $segment, not $key, so it doesn't shadow the method's $key parameter)
            foreach ($keys as $segment) {
                if (is_array($data)) {
                    if (isset($data[$segment])) {
                        $data = $data[$segment];
                    } else {
                        return $default;
                    }
                } elseif (is_object($data)) {
                    if (isset($data->$segment)) {
                        $data = $data->$segment;
                    } else {
                        return $default;
                    }
                } else {
                    // If data is neither array nor object, return default
                    return $default;
                }
            }
        }

        // Return the final value found
        return $data;
    }

    /**
     * Checks if a key exists in the data using dot notation.
     *
     * @param array|\StdClass &$data The data structure to check.
     * @param string $key The dot-notated key.
     * @return bool True if the key exists, false otherwise.
     */
    public static function isset(array|\StdClass &$data, string $key): bool
    {
        // a freshly created object is a safe "not found" marker: nothing stored in
        // $data can ever be === to it. This class is documented as standalone/
        // self-contained, so it shouldn't depend on the orange\framework Application
        // bootstrap having already run and defined the global UNDEFINED constant.
        $notFound = new \stdClass();

        return static::get($data, $key, $notFound) !== $notFound;
    }

    /**
     * Unset a key in the data using dot notation.
     *
     * @param array|\StdClass &$data The data structure to modify.
     * @param string $key The dot-notated key to unset.
     * @return void
     */
    public static function unset(array|\StdClass &$data, string $key): void
    {
        // Check if the key is simple (no delimiter)
        if (!str_contains($key, static::$delimiter)) {
            if (is_object($data)) {
                unset($data->$key);
            } else {
                unset($data[$key]);
            }
        } else {
            // Split the key into parts
            $keys = explode(static::$delimiter, $key);

            // Navigate to the parent of the key to unset
            $current = &$data;
            // Remove the last key
            $lastKey = array_pop($keys);
            foreach ($keys as $k) {
                if (is_object($current)) {
                    if (!isset($current->$k)) {
                        // Path doesn't exist, nothing to unset
                        return;
                    }
                    $current = &$current->$k;
                } else {
                    if (!isset($current[$k])) {
                        // Path doesn't exist
                        return;
                    }
                    $current = &$current[$k];
                }
            }
            // Unset the final key
            if (is_object($current)) {
                unset($current->$lastKey);
            } else {
                unset($current[$lastKey]);
            }
        }
    }

    /**
     * Flattens a nested array or StdClass object into a single-level array with dot-notated keys.
     *
     * @param array|\StdClass $array The nested array or object to flatten.
     * @param string $prepend Internal parameter for recursion, the current key prefix.
     * @return array The flattened array with dot-notated keys.
     */
    public static function flatten(array|\StdClass $array, string $prepend = ''): array
    {
        $flatten = [];

        // Convert objects into arrays for easier traversal
        $iterable = is_object($array) ? (array) $array : $array;

        foreach ($iterable as $key => $value) {
            if ((is_array($value) || $value instanceof \StdClass) && !empty($value)) {
                // Recursively flatten nested arrays/objects, prepending the current key with delimiter
                $flatten[] = static::flatten($value, $prepend . $key . static::$delimiter);
            } else {
                // Add the key-value pair with the full dot-notated key
                $flatten[] = [$prepend . $key => $value];
            }
        }

        // Merge all the flattened arrays into one
        return array_merge(...$flatten);
    }

    /**
     * Expands a flat array or StdClass with dot-notated keys into a nested array structure.
     *
     * @param array|\StdClass $array The flat array or object with dot-notated keys.
     * @return array The nested array.
     */
    public static function expand(array|\StdClass $array): array
    {
        $newArray = [];

        // Convert objects into arrays for easier traversal
        $iterable = is_object($array) ? (array) $array : $array;

        foreach ($iterable as $key => $value) {
            $dots = explode(static::$delimiter, (string) $key);

            if (count($dots) > 1) {
                // For dot-notated keys, build the nested structure
                $last = &$newArray[$dots[0]];

                foreach ($dots as $k => $dot) {
                    if ($k == 0) {
                        // Skip the first dot since it's already set
                        continue;
                    }

                    // A prior key in this same input may have already set this exact
                    // path to a scalar/object leaf (e.g. 'a' => 'x' alongside
                    // 'a.b' => 'y') - nesting under that would otherwise crash with a
                    // cryptic native "Cannot access offset of type string on string"
                    if (isset($last) && !is_array($last)) {
                        throw new InvalidValue('Cannot expand "' . $key . '": "' . implode(static::$delimiter, array_slice($dots, 0, $k)) . '" is already set to a non-array value.');
                    }

                    // Navigate deeper into the array
                    $last = &$last[$dot];
                }

                // Set the final value
                $last = $value;
            } else {
                // For non-dot keys, set directly
                $newArray[$key] = $value;
            }
        }

        return $newArray;
    }
}
