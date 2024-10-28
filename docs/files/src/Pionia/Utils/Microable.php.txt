<?php

namespace Pionia\Utils;

use BadMethodCallException;
use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

trait Microable
{

    protected static array $macros = [];

    /**
     * Add a new mixable to the macros
     * @param string $key
     * @param $value
     * @return void
     */
    public static function macro(string $key, $value): void
    {
        static::$macros[$key] = $value;
    }

    /**
     * Mix another object into the class.
     *
     * @param object $mixin
     * @param bool $replace
     *
     * @throws ReflectionException
     */
    public static function mixin(object $mixin, bool $replace = true): void
    {
        $methods = (new ReflectionClass($mixin))->getMethods(
            ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED
        );

        foreach ($methods as $method) {
            if ($replace || ! static::hasMacro($method->name)) {
                static::macro($method->name, $method->invoke($mixin));
            }
        }
    }

    /**
     * Check if we have a method in the balonables
     * @param string $name
     * @return bool
     */
    public static function hasMacro(string $name): bool
    {
        return isset(static::$macros[$name]);
    }

    /**
     * Remove all the macros we had registered
     */
    public static function flushMacros(): void
    {
        static::$macros = [];
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
        }

        $air = static::$macros[$method];

        if ($air instanceof Closure) {
            $air = $air->bindTo(null, static::class);
        }

        return $air(...$parameters);
    }

    /**
     * Dynamically handle calls to the class.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        if (! static::hasMacro($method)) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
        }

        $air = static::$macros[$method];

        if ($air instanceof Closure) {
            $air = $air->bindTo($this, static::class);
        }

        return $air(...$parameters);
    }

}
