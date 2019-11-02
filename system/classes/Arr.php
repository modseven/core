<?php
/**
 * Array helper.
 *
 * @package    Modseven
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

use Traversable;

class Arr
{
    /**
     * default delimiter for path()
     * @var string
     */
    public static string $delimiter = '.';

    /**
     * Fill an array with a range of numbers.
     *
     * @param integer $step stepping
     * @param integer $max ending number
     * @return  array
     */
    public static function range(int $step = 10, int $max = 100): array
    {
        if ($step < 1) {
            return [];
        }

        $array = [];
        for ($i = $step; $i <= $max; $i += $step) {
            $array[$i] = $i;
        }

        return $array;
    }

    /**
     * Retrieve a single key from an array. If the key does not exist in the
     * array, the default value will be returned instead.
     *
     * @param mixed $array array to extract from
     * @param string $key key name
     * @param mixed $default default value
     * @return  mixed
     */
    public static function get($array, string $key, $default = NULL)
    {
        return $array[$key] ?? $default;
    }

    /**
     * Retrieves multiple paths from an array. If the path does not exist in the
     * array, the default value will be added instead.
     *
     * @param array $array array to extract paths from
     * @param array $paths list of path
     * @param mixed $default default value
     * @return  array
     */
    public static function extract(array $array, array $paths, $default = NULL): array
    {
        $found = [];
        foreach ($paths as $path) {
            self::set_path($found, $path, self::path($array, $path, $default));
        }

        return $found;
    }

    /**
     * Set a value on an array by path.
     *
     * @param array $array Array to update
     * @param string|array $path Path
     * @param mixed $value Value to set
     * @param string $delimiter Path delimiter
     */
    public static function set_path(array & $array, $path, $value, ?string $delimiter = NULL): void
    {
        if (!$delimiter) {
            // Use the default delimiter
            $delimiter = static::$delimiter;
        }

        // The path has already been separated into keys
        $keys = $path;
        if (!is_array($path)) {
            // Split the keys by delimiter
            $keys = explode($delimiter, $path);
        }

        // Set current $array to inner-most array path
        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (ctype_digit($key)) {
                // Make the key an integer
                $key = (int)$key;
            }

            if (!isset($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        // Set key on inner-most array
        $array[array_shift($keys)] = $value;
    }

    /**
     * Gets a value from an array using a dot separated path.
     *
     * @param array $array array to search
     * @param mixed $path key path string (delimiter separated) or array of keys
     * @param mixed $default default value if the path is not set
     * @param string $delimiter key path delimiter
     * @return  mixed
     */
    public static function path(array $array, $path, $default = NULL, ?string $delimiter = NULL)
    {
        if (!self::is_array($array)) {
            // This is not an array!
            return $default;
        }

        if (is_array($path)) {
            // The path has already been separated into keys
            $keys = $path;
        } else {
            if (array_key_exists($path, $array)) {
                // No need to do extra processing
                return $array[$path];
            }

            if ($delimiter === NULL) {
                // Use the default delimiter
                $delimiter = static::$delimiter;
            }

            // Remove starting delimiters and spaces
            $path = ltrim($path, "{$delimiter} ");

            // Remove ending delimiters, spaces, and wildcards
            $path = rtrim($path, "{$delimiter} *");

            // Split the keys by delimiter
            $keys = explode($delimiter, $path);
        }

        do {
            $key = array_shift($keys);

            if (ctype_digit($key)) {
                // Make the key an integer
                $key = (int)$key;
            }

            if (isset($array[$key])) {
                if ($keys) {
                    if (self::is_array($array[$key])) {
                        // Dig down into the next part of the path
                        $array = $array[$key];
                    } else {
                        // Unable to dig deeper
                        break;
                    }
                } else {
                    // Found the path requested
                    return $array[$key];
                }
            } elseif ($key === '*') {
                // Handle wildcards

                $values = [];
                foreach ($array as $arr) {
                    if ($value = self::path($arr, implode('.', $keys))) {
                        $values[] = $value;
                    }
                }

                if ($values) {
                    // Found the values requested
                    return $values;
                }

                break;
            } else {
                // Unable to dig deeper
                break;
            }
        } while ($keys);

        // Unable to find the value requested
        return $default;
    }

    /**
     * Test if a value is an array with an additional check for array-like objects.
     *
     * @param mixed $value value to check
     * @return  boolean
     */
    public static function is_array($value): bool
    {
        if (is_array($value)) {
            // Definitely an array
            return TRUE;
        }

        // Possibly a Traversable object, functionally the same as an array
        return (is_object($value) && $value instanceof Traversable);
    }

    /**
     * Retrieves muliple single-key values from a list of arrays.
     *
     * [!!] A list of arrays is an array that contains arrays, eg: array(array $a, array $b, array $c, ...)
     *
     * @param array $array list of arrays to check
     * @param string $key key to pluck
     * @return  array
     */
    public static function pluck(array $array, string $key): array
    {
        $values = [];

        foreach ($array as $row) {
            if (isset($row[$key])) {
                // Found a value in this row
                $values[] = $row[$key];
            }
        }

        return $values;
    }

    /**
     * Adds a value to the beginning of an associative array.
     *
     * @param array $array array to modify
     * @param string $key array key name
     * @param mixed $val array value
     * @return  array
     */
    public static function unshift(array & $array, string $key, $val): array
    {
        $array = array_reverse($array, TRUE);
        $array[$key] = $val;
        $array = array_reverse($array, TRUE);

        return $array;
    }

    /**
     * Recursive version of [array_map](http://php.net/array_map), applies one or more
     * callbacks to all elements in an array, including sub-arrays.
     *
     * [!!] Because you can pass an array of callbacks, if you wish to use an array-form callback
     * you must nest it in an additional array as above. Calling Arr::map(array($this,'filter'), $array)
     * will cause an error.
     * [!!] Unlike `array_map`, this method requires a callback and will only map
     * a single array.
     *
     * @param mixed $callbacks array of callbacks to apply to every element in the array
     * @param array $array array to map
     * @param array $keys array of keys to apply to
     * @return  array
     */
    public static function map($callbacks, array $array, ?array $keys = NULL): array
    {
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $array[$key] = self::map($callbacks, $array[$key], $keys);
            } elseif (!is_array($keys) || in_array($key, $keys, true)) {
                if (is_array($callbacks)) {
                    foreach ($callbacks as $cb) {
                        $array[$key] = $cb($array[$key]);
                    }
                } else {
                    $array[$key] = $callbacks($array[$key]);
                }
            }
        }

        return $array;
    }

    /**
     * Recursively merge two or more arrays. Values in an associative array
     * overwrite previous values with the same key. Values in an indexed array
     * are appended, but only when they do not already exist in the result.
     *
     * Note that this does not work the same as [array_merge_recursive](http://php.net/array_merge_recursive)!
     *
     * @param array $array1 initial array
     * @param array $array2,... array to merge
     * @return  array
     */
    public static function merge(array $array1, array $array2): array
    {
        if (self::is_assoc($array2)) {
            foreach ($array2 as $key => $value) {
                if (is_array($value)
                    && isset($array1[$key])
                    && is_array($array1[$key])
                ) {
                    $array1[$key] = self::merge($array1[$key], $value);
                } else {
                    $array1[$key] = $value;
                }
            }
        } else {
            foreach ($array2 as $value) {
                if (!in_array($value, $array1, TRUE)) {
                    $array1[] = $value;
                }
            }
        }

        if (func_num_args() > 2) {
            foreach (array_slice(func_get_args(), 2) as $array3) {
                if (self::is_assoc($array3)) {
                    foreach ($array3 as $key => $value) {
                        if (is_array($value)
                            && isset($array1[$key])
                            && is_array($array1[$key])
                        ) {
                            $array1[$key] = self::merge($array1[$key], $value);
                        } else {
                            $array1[$key] = $value;
                        }
                    }
                } else {
                    foreach ($array3 as $value) {
                        if (!in_array($value, $array1, TRUE)) {
                            $array1[] = $value;
                        }
                    }
                }
            }
        }

        return $array1;
    }

    /**
     * Tests if an array is associative or not.
     *
     * @param array $array array to check
     * @return  boolean
     */
    public static function is_assoc(array $array): bool
    {
        // Keys of the array
        $keys = array_keys($array);

        // If the array keys of the keys match the keys, then the array must
        // not be associative (e.g. the keys array looked like {0:0, 1:1...}).
        return array_keys($keys) !== $keys;
    }

    /**
     * Overwrites an array with values from input arrays.
     * Keys that do not exist in the first array will not be added!
     *
     * @param array $array1 master array
     * @param array $array2 input arrays that will overwrite existing values
     * @return  array
     */
    public static function overwrite(array $array1, array $array2): array
    {
        foreach (array_intersect_key($array2, $array1) as $key => $value) {
            $array1[$key] = $value;
        }

        if (func_num_args() > 2) {
            foreach (array_slice(func_get_args(), 2) as $array3) {
                foreach (array_intersect_key($array3, $array1) as $key => $value) {
                    $array1[$key] = $value;
                }
            }
        }

        return $array1;
    }

    /**
     * Creates a callable function and parameter list from a string representation.
     * Note that this function does not validate the callback string.
     *
     * @param string $str callback string
     * @return  array   function, params
     */
    public static function callback(string $str): array
    {
        // Overloaded as parts are found
        $params = NULL;

        // command[param,param]
        if (preg_match('/^([^(]*+)\((.*)\)$/', $str, $match)) {
            // command
            $command = $match[1];

            if ($match[2] !== '') {
                // param,param
                $params = preg_split('/(?<!\\\\),/', $match[2]);
                $params = str_replace('\,', ',', $params);
            }
        } else {
            // command
            $command = $str;
        }

        if (strpos($command, '::') !== FALSE) {
            // Create a static method callable command
            $command = explode('::', $command, 2);
        }

        return [$command, $params];
    }

    /**
     * Convert a multi-dimensional array into a single-dimensional array.
     *
     * [!!] The keys of array values will be discarded.
     *
     * @param array $array array to flatten
     * @return  array
     */
    public static function flatten(array $array): array
    {
        $is_assoc = self::is_assoc($array);

        $flat = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $flat[] = self::flatten($value);
            } elseif ($is_assoc) {
                $flat[$key] = $value;
            } else {
                $flat[] = $value;
            }
        }
        $flat = array_merge(...$flat);
        return $flat;
    }

}
