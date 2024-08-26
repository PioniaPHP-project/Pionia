<?php

use Pionia\Pionia\Http\Response\BaseResponse;
use Pionia\Pionia\Utils\Arrayable;
use Pionia\Pionia\Utils\HighOrderTapProxy;

if (! function_exists('tap')) {
    /**
     * Call the given Closure with the given value then return the value.
     *
     * @template TValue
     *
     * @param  TValue  $value
     * @param (callable(TValue): mixed)|null $callback
     * @return HighOrderTapProxy|TValue
     */
    function tap($value, callable $callback = null)
    {
        if (is_null($callback)) {
            return new HighOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }
}

if (! function_exists('arr')) {
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param ?array $array
     * @return Arrayable
     */
    function arr(?array $array): Arrayable
    {
        return new Arrayable($array);
    }
}

if (! function_exists('env')) {
    /**
     * Get an item from the environment. Or default if it does not exist.
     *
     * If both the key and the default value are null, the function should return the entire environment.
     */
    function env(?string $key =null, mixed $default = null): mixed
    {
        $env = arr($_SERVER);
        $env->merge($_ENV);
        if ($key === null) {
            return $env;
        }
        return $env->get($key, $default);
    }
}

if (! function_exists('setEnv')) {
    /**
     * Adds a temporary key-value to the environment.
     *
     * This can only be retrieved using the `env` function.
     */
    function setEnv(string $key, mixed $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

if (!function_exists('response')) {
    /**
     * Helper function to return a response
     * @param $returnCode int
     * @param string|null $returnMessage
     * @param mixed $returnData
     * @param mixed $extraData
     * @return BaseResponse
     */
    function response(int $returnCode = 0, ?string $returnMessage = null, mixed $returnData = null, mixed $extraData = null): BaseResponse
    {
        return BaseResponse::jsonResponse($returnCode, $returnMessage, $returnData, $extraData);
    }
}
