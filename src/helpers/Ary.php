<?php

declare(strict_types=1);

namespace orange\framework\helpers;

class Ary
{
    /**
     * Remaps array keys based on a provided mapping array.
     *
     * @param array $input The input array to remap.
     * @param array $map An associative array where keys are old keys and values are new keys.
     * @return array The array with remapped keys.
     */
    public static function remapKey(array $input, array $map): array
    {
        foreach ($input as $key => $value) {
            if (isset($map[$key])) {
                $input[$map[$key]] = $value;
                unset($input[$key]);
            }
        }

        return $input;
    }

    /**
     * Remaps array values based on a provided mapping array.
     *
     * @param array $input The input array to remap.
     * @param array $map An associative array where keys are old values and values are new values.
     * @return array The array with remapped values.
     */
    public static function remapValue(array $input, array $map): array
    {
        foreach ($input as $key => $value) {
            if (isset($map[$value])) {
                $input[$key] = $map[$value];
            }
        }

        return $input;
    }

    /**
     * Wraps an array of strings with prefixes, suffixes, and separators for output.
     *
     * @param array $array The array of strings to wrap.
     * @param string $prefix The prefix to add to each string.
     * @param string $suffix The suffix to add to each string.
     * @param string $separator The separator between wrapped strings.
     * @param string $parentPrefix The prefix for the entire output.
     * @param string $parentSuffix The suffix for the entire output.
     * @return string The wrapped and joined string.
     */
    public static function wrapArray(array $array, string $prefix = '', string $suffix = '', string $separator = '', string $parentPrefix = '', string $parentSuffix = ''): string
    {
        $output = [];

        foreach ($array as $string) {
            $output[] = $prefix . $string . $suffix;
        }

        return $parentPrefix . implode($separator, $output) . $parentSuffix;
    }

    /**
     * Collapses an array of arrays or objects into an associative array.
     *
     * @param array $array The input array of arrays or objects.
     * @param string $key The key to use as the associative key (default 'id').
     * @param string $value The key to use as the value; use '*' for the entire row (default '*').
     * @param string|null $sort Sort the result: 'asc'/'a' (alias for ksort), 'desc'/'d' (alias for krsort),
     *                     or any of 'asort', 'arsort', 'ksort', 'krsort', 'natcasesort', 'natsort',
     *                     'shuffle', 'sort', 'rsort' to call that function directly; null for no sort.
     * @param int $flags Optional flags passed as the second argument to the sort function; -1 (default) omits the argument.
     * @return array The associative array.
     * @throws \Exception If $sort is set but doesn't match a known sort option.
     */
    public static function makeAssociated(array $array, string $key = 'id', string $value = '*', ?string $sort = null, int $flags = -1): array
    {
        $associativeArray = [];

        foreach ($array as $row) {
            if (is_object($row)) {
                if ($value == '*') {
                    $associativeArray[$row->$key] = $row;
                } else {
                    $associativeArray[$row->$key] = $row->$value;
                }
            } else {
                if ($value == '*') {
                    $associativeArray[$row[$key]] = $row;
                } else {
                    $associativeArray[$row[$key]] = $row[$value];
                }
            }
        }

        if ($sort) {
            $sortMethods = ['asort', 'arsort', 'ksort', 'krsort', 'natcasesort', 'natsort', 'shuffle', 'sort', 'rsort'];
            $remap = ['desc' => 'krsort', 'd' => 'krsort', 'asc' => 'ksort', 'a' => 'ksort'];

            if (isset($remap[$sort])) {
                $sortFunction = $remap[$sort];
            } elseif (in_array($sort, $sortMethods)) {
                $sortFunction = $sort;
            } else {
                throw new \Exception('Could not determine sort method: ' . $sort);
            }

            if ($flags === -1) {
                $sortFunction($associativeArray);
            } else {
                $sortFunction($associativeArray, $flags);
            }
        }

        return $associativeArray;
    }

    /**
     * Element
     *
     * Lets you determine whether an array index is set and whether it has a value.
     * If the element is empty it returns NULL (or whatever you specify as the default value.)
     *
     * @param string $item The key to check in the array.
     * @param array $array The array to search.
     * @param mixed $default The default value to return if the key is not set.
     * @return mixed The value of the array key or the default.
     */
    public static function element(string $item, array $array, mixed $default = null): mixed
    {
        return array_key_exists($item, $array) ? $array[$item] : $default;
    }

    // ------------------------------------------------------------------------

    /**
     * Random Element - Takes an array as input and returns a random element
     *
     * @param array $array The input array.
     * @return mixed A random element from the array.
     */
    public static function randomElement(array $array)
    {
        return $array[array_rand($array)];
    }

    // --------------------------------------------------------------------

    /**
     * Elements
     *
     * Returns only the array items specified. Will return a default value if
     * it is not set.
     *
     * @param mixed $items The key(s) to retrieve; can be a string or array of strings.
     * @param array $array The array to search.
     * @param mixed $default The default value to return for missing keys.
     * @return mixed An array of the requested items with defaults for missing ones.
     */
    public static function elements($items, array $array, mixed $default = null): mixed
    {
        $return = [];

        is_array($items) || $items = [$items];

        foreach ($items as $item) {
            $return[$item] = array_key_exists($item, $array) ? $array[$item] : $default;
        }

        return $return;
    }
}
