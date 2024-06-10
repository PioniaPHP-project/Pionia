<?php

namespace Pionia\core\helpers;

use ReflectionClass;

/**
 * These are just helpers to quickly get staff done
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class Utilities
{
    /**
     * This function converts an array to a comma-separated string
     *
     * @param array $array The array to convert
     * @param string $separator The separator to use
     * @return string The comma-separated string
     */
    public static function arrayToCommaSepString(array $array, $separator = ','): string
    {
        return implode($separator, $array);
    }

    /**
     * This function json encodes a value
     * @param mixed $value The value to encode
     * @return string The json encoded value
     */
    public static function jsonify(mixed $value): string
    {
        return json_encode($value);
    }

    /**
     * This function checks if a class extends another class
     * @param string $klass The class to check
     * @param string $baseObj The base class to check against
     * @return bool|string True if the class extends the base class, or 'NO_CLASS' if the class does not exist, or 'DOES_NOT' if the class does not extend the base class
     */
    public static function extends(string $klass, string $baseObj): bool|string
    {
        if (!class_exists($klass)) {
            return 'NO_CLASS';
        }
        $reflectionClass = new ReflectionClass($klass);

        if (!$reflectionClass->isSubclassOf($baseObj)) {
            return 'DOES_NOT';
        }
        return true;
    }

    /**
     * This function checks if a class implements an interface
     * @param string $class The class to check
     * @param string $interface The interface to check against
     * @return bool|string True if the class implements the interface, or 'NO_CLASS' if the class does not exist, or 'DOES_NOT' if the class does not implement the interface
     */
    public static function implements(string $class, string $interface): bool | string
    {
        if (!class_exists($class)) {
            return 'NO_CLASS';
        }
        $reflectionClass = new ReflectionClass($class);
        if (!$reflectionClass->implementsInterface($interface))
        {
            return 'DOES_NOT';
        }
        return true;
    }

}
