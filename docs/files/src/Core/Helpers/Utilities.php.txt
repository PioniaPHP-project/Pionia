<?php

namespace Pionia\Core\Helpers;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
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


    /**
     * Camel cases any string you drop at it
     * @param string $string
     * @return string
     */
    public static function toCamelCase(string $string): string
    {
        return self::formatter()->camelize($string);
    }

    /**
     * Converts any word to its singular form
     * @param string $word
     * @return string
     */
    public static function singularize(string $word): string
    {
        return self::formatter()->singularize($word);
    }

    /**
     * Converts any word to its plural form
     * @param string $word
     * @return string
     */
    public static function pluralize(string $word): string
    {
        return self::formatter()->pluralize($word);
    }

    /**
     * Capitalizes any word eg hello world => Hello World
     * @param string $word
     * @return string
     */
    public static function capitalize(string $word): string
    {
        return self::formatter()->capitalize($word);
    }

    /**
     * Gives you a chance to teleport to the core symfony inflector
     */
    public static function formatter(): Inflector
    {
        return InflectorFactory::create()->build();
    }

    /**
     * Converts a string to a class name eg hello_world => HelloWorld
     * @param string $class
     * @return string
     */
    public static function classify(string $class): string
    {
        return self::formatter()->classify($class);
    }

    /**
     * Converts a string to a table name eg HelloWorld => hello_world
     * @param string $class
     * @return string
     */
    public static function modelize(string $class): string
    {
        return self::formatter()->tableize($class);
    }

    /**
     * Converts a string to a slug eg HelloWorld => hello-world
     * @param string $class
     * @return string
     */
    public static function slugify(string $class): string
    {
        return self::formatter()->urlize($class);
    }
}
