<?php

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
