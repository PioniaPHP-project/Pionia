<?php

namespace Pionia\Realm;

use InvalidArgumentException;
use Pionia\Base\WebApplication;
use Pionia\Collections\Arrayable;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

trait ContainableRealm
{
    /**
     * Application container context
     * @var ?ContainerInterface
     */
    public ?ContainerInterface $context;


    /**
     * Check if a value exists in the container
     * @param string $key
     * @return bool
     */
    public function contextHas(string $key): bool
    {
        return $this->has($key);
    }

    public function has($key): bool
    {
        return $this->context->has($key);
    }

    public function get($key): mixed
    {
        return $this->context->get($key);
    }

    /**
     * Set a value in the container
     */
    public function set(string $name, mixed $value): static
    {
        $this->context->set($name, $value);
        return $this;
    }

    public function make(string $name, array $parameters = [])
    {
        return $this->context->make($name, $parameters);
    }

    public function injectOn(object $instance) : object
    {
        return $this->context->injectOn($instance);
    }

    public function call($callable, array $parameters = []) : mixed
    {
        return $this->context->call($callable, $parameters);
    }

    public function getKnownEntryNames() : array
    {
        return $this->context->getKnownEntryNames();
    }

    public function debugEntry(string $name) : string
    {
        return $this->context->debugEntry($name);
    }

    /**
     * Get a value from the container or return null instead of throwing an exception
     */
    public function getSilently(mixed $key): mixed
    {
        if ($this->contextHas($key)) {
            return $this->getOrFail($key);
        }
        return null;
    }

    /**
     * Get a value from the container or return a default value
     */
    public function getOrDefault(string $key, mixed $default): mixed
    {
        return $this->getSilently($key) ?? $default;
    }

    /**
     * Get a value from the container or throw an exception.
     *
     * @param string $key
     * @return mixed
     *@see WebApplication::resolve() for similar functionality on the application instance
     *
     */
    public function getOrFail(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Make an instance of a class from the container. This will throw an exception if the class is not found
     */
    public function contextMake(string $name, array $parameters = []): mixed
    {
        return $this->make($name, $parameters);
    }

    /**
     * Create an instance of a class without throwing an exception when it fails
     * @param string $name
     * @param array $parameters
     * @return mixed
     */
    public function contextMakeSilently(string $name, array $parameters = []): mixed
    {
        try {
            return $this->contextMake($name, $parameters);
        } catch (ContainerExceptionInterface | NotFoundExceptionInterface $e) {
            return null;
        }
    }

    /**
     * @param string $contextKey
     * @param array|null $dataToAdd
     * @return ContainableRealm
     */
    public function contextArrAdd(string $contextKey, ?array $dataToAdd = []): static
    {
        if (!$dataToAdd){
            return $this;
        }
        if ($this->contextHas($contextKey)) {
            $data = $this->getSilently($contextKey);
            if (is_a($data, Arrayable::class)) {
                $data->merge($dataToAdd);
                $this->set($contextKey, $data);
            } else {
                throw new InvalidArgumentException("The data in the context key $contextKey is not an instance of Arrayable");
            }
        } else {
            $this->set($contextKey, $dataToAdd);
        }

        return $this;
    }

}
