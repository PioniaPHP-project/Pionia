<?php

namespace JetPhp\Core\Helpers;

use ReflectionClass;

/**
 * These are just helpers to quickly staff done
 */
class Utilities
{
    public static function arrayToCommaSepString(array $array, $separator = ','): string
    {
        return implode($separator, $array);
    }

    public static function jsonify(mixed $value): string
    {
        return json_encode($value);
    }

    public static function extends($klass, $baseObj): bool|string
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

    public static function implements($class, $interface): bool | string
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
