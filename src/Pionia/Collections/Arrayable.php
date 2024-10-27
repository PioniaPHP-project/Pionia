<?php

namespace Pionia\Collections;

use Exception;
use JsonException;
use Pionia\Utils\Microable;
use ReflectionClass;
use Throwable;

class Arrayable
{
    use Microable;

    /**
     * The array to be manipulated
     * @var array
     */
    private array $array = [];

    public function __construct(array $array = [])
    {
        if ($this->arrayType($array) === 'indexed') {
            foreach ($array as $key => $value) {
                $this->array[(string)$key] = $value;
            }
        } else {
            $this->array = $array;
        }
    }

    /**
     * Return the type of the underlying array object
     */
    public function type(): string
    {
        if ($this->isEmpty()) {
            return 'EMPTY';
        }
        return strtoupper($this->arrayType());
    }

    /**
     * Checks if an array is associative
     * @param array|null $arr
     * @return bool
     */
    public function isAssoc(?array $arr = null): bool
    {
        $arr = $arr ?? $this->array;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * Checks if an array has both numeric and string keys
     * @param array|null $arr
     * @return bool
     */
    public function isMixed(?array $arr = null): bool
    {
        $arr = $arr ?? $this->array;
        $hasNumericKey = false;
        $hasStringKey = false;

        foreach ($arr as $key => $value) {
            if (is_int($key)) {
                $hasNumericKey = true;
            } elseif (is_string($key)) {
                $hasStringKey = true;
            }

            if ($hasNumericKey && $hasStringKey) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the type of the array
     * @param array|null $arr
     * @return string
     */
    public function arrayType(?array $arr = null): string
    {
        $arr = $arr ?? $this->array;
        if ($this->isMixed($arr)) {
            return 'mixed';
        } else if ($this->isAssoc($arr)) {
            return 'associative';
        }
        return 'indexed';
    }

    /**
     * Get the keys of the array
     * @return array
     */
    public function keys(): array
    {
        return array_keys($this->array);
    }

    /**
     * Get the values of the array
     * @return array
     */
    public function values(): array
    {
        return array_values($this->array);
    }

    /**
     * Map through the array
     * @param callable $callback
     * @return Arrayable
     */
    public function map(callable $callback): static
    {
        $this->array = array_map($callback, $this->array);
        return $this;
    }

    /**
     * Filter the array
     * @param ?callable $callback
     * @return Arrayable
     */
    public function filter(?callable $callback = null): static
    {
        $this->array = array_filter($this->array, $callback);
        return $this;
    }

    /**
     * Reduce the array
     * @param callable $callback
     * @param null $initial
     * @return Arrayable
     */
    public function reduce(callable $callback, mixed $initial = null): static
    {
        $this->array = array_reduce($this->array, $callback, $initial);
        return $this;
    }

    /**
     * Each through the array
     * @param callable $callback
     * @return Arrayable
     */
    public function each(callable $callback): static
    {
        foreach ($this->array as $key => $value) {
            $callback($value, $key);
        }
        return $this;
    }

    /**
     * Sort the array
     * @param callable $callback
     * @return Arrayable
     */
    public function sort(callable $callback): static
    {
        usort($this->array, $callback);
        return $this;
    }

    /**
     * Sort the keys of the array
     * @param callable $callback
     * @return Arrayable
     */
    public function sortKeys(callable $callback): static
    {
        uksort($this->array, $callback);
        return $this;
    }

    /**
     * Sort the values of the array
     * @param callable $callback
     * @return Arrayable
     */
    public function sortValues(callable $callback): static
    {
        uasort($this->array, $callback);
        return $this;
    }

    /**
     * Reverse the array
     * @return Arrayable
     */
    public function reverse(): static
    {
        $this->array = array_reverse($this->array);
        return $this;
    }


    /**
     * Slice the array
     * @param int $offset
     * @param int|null $length
     * @param $preserve_keys
     * @return $this
     */
    public function slice(int $offset, int $length = null, $preserve_keys = false): static
    {
        $this->array = array_slice($this->array, $offset, $length, $preserve_keys);
        return $this;
    }

    /**
     * Chunk the array
     * @param int $size
     * @param bool $preserve_keys
     * @return Arrayable
     */
    public function chunk(int $size, bool $preserve_keys = false): static
    {
        $this->array = array_chunk($this->array, $size, $preserve_keys);
        return $this;
    }

    /**
     * Convert the keys of the array to lowercase
     * @return $this
     */
    public function keysToLowerCase(): static
    {
        $this->array = array_change_key_case($this->array, CASE_LOWER);
        return $this;
    }

    /**
     * Convert the keys of the array to uppercase
     * @return $this
     */
    public function keysToUpperCase(): static
    {
        $this->array = array_change_key_case($this->array, CASE_UPPER);
        return $this;
    }


    /**
     * Convert the values of the array to lowercase
     * @return Arrayable
     */
    public function valuesToLowerCase(): static
    {
        $this->array = array_map('strtolower', $this->array);
        return $this;
    }

    /**
     * Convert the arrayable object to an array
     * @return array
     */
    public function toArray(): array
    {
        return $this->array;
    }

    /**
     * Get the first item in the array
     * @return false|mixed
     */
    public function first(): mixed
    {
        $item = reset($this->array);
        return $item === false ? null : $item;
    }

    /**
     * Get the last item in the array
     * @return false|mixed
     */
    public function last(): mixed
    {
        return end($this->array);
    }

    /**
     * Convert the arrayable to json
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->array);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Get an item from the array
     *
     * If checkUpperAndLower is set to true, the method will check for uppercase and lowercase cases
     * @param ?string $name
     * @param mixed|null $default
     * @return mixed
     */
    public function get(?string $name, mixed $default = null): mixed
    {
        if (!isset($name)) {
            return $this->toArray();
        }

        if ($this->has($name)) {
            return $this->array[$name] ?? $this->array[strtolower($name)] ?? $this->array[strtoupper($name)];
        }

        return $default;
    }

    /**
     * Set an item in the array by key and value
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function set(string $key, mixed $value): static
    {
        $this->array[$key] = $value;
        return $this;
    }

    /**
     * Adds the $value to the current value of $key.
     * If the value is numeric, it will be added to the current value
     * If the value is a string, it will be concatenated to the current value
     * If the value is an array, it will be merged with the current value
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function addAtKey(string $key, mixed $value): static
    {
        $current = $this->get($key);
        if (is_array($current) && is_array($value)) {
            $this->array[$key] = array_merge($current, $value);
        } else if (is_string($current) && is_string($value)) {
            $this->array[$key] .= $value;
        } else if (is_numeric($current) && is_numeric($value)) {
            $this->array[$key] += $value;
        } else {
           $this->set($key, $value);
        }

        return $this;
    }

    /**
     * @throws Throwable
     */
    public function getOrThrow(string $key, Throwable|string|null $exception = null)
    {
        $value = $this->get($key);
        if (empty($value)) {
            if (is_string($exception)) {
                throw new Exception($exception);
            } else if ($exception instanceof Throwable) {
                throw $exception;
            } else {
                throw new Exception("$key not found");
            }
        }
        return $value;
    }

    /**
     * Get the size of the array
     * @return int
     */
    public function size(): int
    {
        return count($this->array);
    }

    /**
     * Checks if the array is empty
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->size() === 0;
    }

    public function isFilled(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Add a key value pair to the array
     * If only the key is provided, the key will be the value
     * @param string $key
     * @param mixed|null $value
     * @return Arrayable
     */
    public function add(string $key, mixed $value = null): static
    {
        if (!isset($value)) {
            $this->array[$key] = $key;
        }else {
            $this->array[$key] = $value;
        }
        return $this;
    }

    /**
     * Remove an item from the array
     * @param string $key
     * @return Arrayable
     */
    public function remove($key): static
    {
        unset($this->array[$key]);
        return $this;
    }

    /**
     * Check if the array has a key
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->array[$key])
            || isset($this->array[strtolower($key)])
            || isset($this->array[strtoupper($key)])
            || array_key_exists($key, $this->array)
            || array_key_exists(strtolower($key), $this->array)
            || array_key_exists(strtoupper($key), $this->array);
    }

    public function __get($name)
    {
        return $this->get($name);
    }

    public function __set($name, $value)
    {
        $this->add($name, $value);
    }

    public function __isset($name)
    {
        return $this->has($name);
    }

    public function __unset($name)
    {
        return $this->remove($name);
    }

    /**
     * Merge a whole into the current array
     * @param array|Arrayable $array $array
     * @return Arrayable
     */
    public function merge(array | Arrayable $array): static
    {
        if ($array instanceof Arrayable){
            return $this->merge($array->toArray());
        }
        $this->array = array_merge($this->array, $array);
        return $this;
    }

    public static function toArrayable(array $array): Arrayable
    {
        return new Arrayable($array);
    }

    public function addAfter($positionKey, $key, $value = null): static
    {
        $array = [];
        foreach ($this->array as $k => $v) {
            $array[$k] = $v;
            if ($k === $positionKey) {
                $array[$key] = $value ?? $key;
            }
        }
        $this->array = $array;
        return $this;
    }

    public function addBefore(string $positionKey, string $key, mixed $value = null): static
    {
        $array = [];
        foreach ($this->array as $k => $v) {
            if ($k === $positionKey) {
                $array[$key] = $value ?? $key;
            }
            $array[$k] = $v;
        }
        $this->array = $array;
        return $this;
    }

    public function replace(string $key, mixed $value = null): static
    {
        $this->array[$key] = $value ?? $key;
        return $this;
    }

    public function shift(): mixed
    {
        return array_shift($this->array);
    }

    public function pop(): mixed
    {
        return array_pop($this->array);
    }

    public function unshift(array $value): static
    {
        if ($this->arrayType($value) === 'indexed') {
            $arr = [];
            foreach ($value as $key) {
                $arr[(string)$key] = $key;
            }
        } else {
            $arr = $value;
        }
        $this->array = array_merge($arr, $this->array);
        return $this;
    }

    public function all(): array
    {
        return $this->toArray();
    }

    /**
     * Reset the array
     * @return $this
     */
    public function flush(): static
    {
        $this->array = [];
        return $this;
    }

    /**
     * Get the value at a certain index
     * @param int $index
     * @return mixed
     */
    public function at(int $index): mixed
    {
        $keys = array_keys($this->array);
        return $this->array[$keys[$index]] ?? null;
    }

    /**
     * Map through the array and flatten the result to a single array of values by using the callback
     * @example ```php
     * $this->arrayable->merge([
     * [
     * 'first' => 'John',
     * 'last' => 'Doe',
     * 'age' => 22
     * ],
     * [
     * 'first' => 'Kate',
     * 'last' => 'Middleton',
     * 'age' => 30
     * ],
     * [
     * 'first' => 'Jane',
     * 'last' => 'Doe',
     * 'age' => 20
     * ]
     * ]);
     *
     * $map = $this->arrayable->mapWithKeys(function ($item) {
     * return [$item['first'] => $item['age']];
     * });
     * dd($map->toArray());
     *
     * // Output
     * [
     * "John" => 22
     * "Kate" => 30
     * "Jane" => 20
     * ]
     * ```
     *
     * @param callable $callback
     * @return Arrayable
     */
    public function mapWithKeys(callable $callback): static
    {
        $result = [];

        foreach ($this->array as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }
        $this->array = $result;
        return $this;
    }

    /**
     * Filter the array using the given callback.
     *
     * @param  callable  $callback
     * @return Arrayable
     */
    public function where(callable $callback): Arrayable
    {
        $this->array = array_filter($this->array, $callback, ARRAY_FILTER_USE_BOTH);
        return $this;
    }

    /**
     * Get a subset of the items from the given array.
     *
     * @param array|string|null $keys
     * @return Arrayable
     */
    public function only(array|string|null $keys = null): Arrayable
    {
        if (is_null($keys)) {
            return new static($this->array);
        }
        if (is_string($keys)) {
            $keys = func_get_args();
        }

        $this->array = array_intersect_key($this->array, array_flip((array) $keys));
        return $this;
    }

    public function getString(string $key): ?string
    {
        $data = $this->get($key);
        if (is_null($data)) {
            return null;
        }
        if (is_array($data)) {
            return json_encode($data);
        }
        return (string)$data;
    }

    public function getInt(string $key): ?int
    {
        $data = $this->get($key);
        if (is_null($data)) {
            return null;
        }
        return (int)$data;
    }

    public function getFloat(string $key): ?float
    {
        $data = $this->get($key);
        if (is_null($data)) {
            return null;
        }
        return (float)$data;
    }

    /**
     * @throws JsonException
     */
    public function getJson($key): false|string|null
    {
        $data = $this->get($key);
        if (is_null($data)) {
            return null;
        }
        if (!is_array($data)) {
            return (string) $data;
        }

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    public function getBool(string $key): ?bool
    {
        $data = $this->get($key);
        if (is_null($data)) {
            return null;
        }
        return (bool)$data;
    }

    public function getArray(string $key): ?array
    {
        $data = $this->get($key);
        if (is_null($data)) {
            return null;
        }
        return (array)$data;
    }

    public function getArrayable(string $key): ?Arrayable
    {
        $data = $this->get($key);
        if (is_null($data)) {
            return null;
        }
        return is_string($data) || is_numeric($data) ? arr([$data]) : arr($data);
    }

    /**
     * @throws Exception
     */
    public function getA(string $key, string $className)
    {
        $data = $this->getArray($key);

        if (is_null($data)) {
            return null;
        }

        $ref = new ReflectionClass($className);

        if ($ref->isInstantiable()) {
            return $ref->newInstance(...$data);
        } else {
            throw new Exception("Class $className is not instantiable, do you have a constructor?");
        }
    }

    public function getPositiveInteger(string $key): ?int
    {
        $data = $this->get($key);
        if (is_numeric($data)) {
            $data = (int)$data;
            if ($data > 0) {
                return $data;
            }
        }
        return null;
    }

    public function getNegativeInteger(string $key): ?int
    {
        $data = $this->get($key);
        if (is_numeric($data)) {
            $data = (int)$data;
            if ($data < 0) {
                return $data;
            }
        }
        return null;
    }

    /**
     * Returns the items in the current array that are not in the other array
     * @param Arrayable|array $arrayable
     * @return Arrayable
     */
    public function differenceFrom(Arrayable | array $arrayable): Arrayable
    {
        $diff = array_diff($this->array, $arrayable->all());
        return new Arrayable($diff);
    }
}

