<?php

namespace Pionia\Utils;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Pionia\Base\PioniaApplication;
use ReflectionClass;

class Support
{
    /**
     * This function converts an array to a comma-separated string
     *
     * @param array $array The array to convert
     * @param string $separator The separator to use
     * @return string The comma-separated string
     */
    public static function arrayToString(array $array, $separator = ','): string
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
     */
    public static function extends(string $klass, string $baseObj): bool
    {

        if (!class_exists($klass)) {
            echo "Class $klass does not exist";
            return false;
        }
        $reflectionClass = new ReflectionClass($klass);

        return $reflectionClass->isSubclassOf($baseObj);
    }

    /**
     * This function checks if a class implements an interface
     * @param string $class The class to check
     * @param string $interface The interface to check against
     */
    public static function implements(string $class, string $interface): bool
    {
        if (!class_exists($class)) {
            return false;
        }
        $reflectionClass = new ReflectionClass($class);
        return $reflectionClass->implementsInterface($interface);
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
     * Converts any word to its snake case form
     * @param string $string
     * @return string
     */
    public static function toSnakeCase(string $string): string
    {
        return self::formatter()->tableize($string);
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

    /**
     * Grabs all keys of an array. But ignores nested keys
     * Converts ['name' => 'John', 'age' => 20, 'address' => ['city' => 'Lagos', 'state' => 'Lagos']] to ['name', 'age', 'address']
     * @param array $array
     * @return array
     */
    public static function levelOneKeys(array $array): array
    {
        $keys = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $keys[] = $key;
            } else {
                $keys[] = $value;
            }
        }
        return $keys;
    }

    /**
     * Flattens an array
     * @param array $array
     * @return array
     */
    public static function arrFlatten(array $array): array
    {
        $return = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $return = array_merge($return, self::arrFlatten($value));
            } else {
                $return[$key] = $value;
            }
        }
        return $return;

    }

    /**
     * Creates or updates any section of our database.ini file to the values provided
     * @param string $filePath
     * @param string $section The section to update/create
     * @param array $values The values to update/create
     * @param PioniaApplication|null $application
     * @return void
     */
    public static function updateSettingsFileSection(string $filePath, string $section, array $values, PioniaApplication|null $application = null): void
    {
        if (count($values) > 0 && self::isValidIniFile($filePath)) {
            $config_data = parse_ini_file($filePath, true);

            foreach ($values as $key => $value) {
                $config_data[$section][$key] = $value;
            }

            $new_content = '';
            foreach ($config_data as $section => $section_content) {
                $section_content = array_map(function ($value, $key) {
                    return "$key=$value";
                }, array_values($section_content), array_keys($section_content));
                $section_content = implode("\n", $section_content);
                $new_content .= "[$section]\n$section_content\n\n";
            }

            $file_resource = fopen($filePath, 'w+');
            fwrite($file_resource, "$new_content");
            fclose($file_resource);

            $application?->refreshEnv();
        }
    }

    /**
     * Checks if a file is a valid ini file
     * @param string $filePath
     * @return bool
     */
    public static function isValidIniFile(string $filePath): bool
    {
        return file_exists($filePath) && str_ends_with($filePath, '.ini');
    }

    /**
     * Removes an entire section from the database.ini file
     * @param string $filePath
     * @param string $section
     * @param PioniaApplication|null $application
     * @return void
     */
    public static function inidelsection(string $filePath, string $section, ?PioniaApplication $application): void
    {
        if (!self::isValidIniFile($filePath)) {
            return;
        }
        $parsed_ini = parse_ini_file($filePath, TRUE);
        $skip = "$section";
        $output = '';
        foreach ($parsed_ini as $section => $info) {
            if ($section != $skip) {
                $output .= "[$section]\n";
                foreach ($info as $var => $val) {
                    $output .= "$var=$val\n";
                }
                $output .= "\n\n";
            }
        }
        $file_resource = fopen($filePath, 'w+');
        fwrite($file_resource, "$output");
        fclose($file_resource);
        $application?->refreshEnv();
    }

    /**
     * Escapes a string to be used as a shell argument.
     *
     * @param  string  $argument
     * @return string
     */
    public static function escapeArgument(string $argument): string
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            if ($argument === '') {
                return '""';
            }

            $escapedArgument = '';
            $quote = false;

            foreach (preg_split('/(")/', $argument, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE) as $part) {
                if ($part === '"') {
                    $escapedArgument .= '\\"';
                } elseif (self::isSurroundedBy($part, '%')) {
                    // Avoid environment variable expansion
                    $escapedArgument .= '^%"'.substr($part, 1, -1).'"^%';
                } else {
                    // escape trailing backslash
                    if (str_ends_with($part, '\\')) {
                        $part .= '\\';
                    }
                    $quote = true;
                    $escapedArgument .= $part;
                }
            }

            if ($quote) {
                $escapedArgument = '"'.$escapedArgument.'"';
            }

            return $escapedArgument;
        }

        return "'".str_replace("'", "'\\''", $argument)."'";
    }

    /**
     * Is the given string surrounded by the given character?
     *
     * @param  string  $arg
     * @param  string  $char
     * @return bool
     */
    protected static function isSurroundedBy(string $arg, string $char): bool
    {
        return strlen($arg) > 2 && $char === $arg[0] && $char === $arg[strlen($arg) - 1];
    }
}

