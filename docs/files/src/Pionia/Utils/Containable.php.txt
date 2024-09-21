<?php

namespace Pionia\Utils;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use InvalidArgumentException;
use Pionia\Base\PioniaApplication;
use Pionia\Collections\Arrayable;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

trait Containable
{
    public function __construct(ContainerInterface | Container | null $context = null)
    {
        $this->context = $context;
    }
    /**
     * Application container context
     * @var ContainerInterface|Container|null
     */
    public ContainerInterface | Container | null $context;


    /**
     * Check if a value exists in the container
     * @param string $key
     * @return bool
     */
    public function contextHas(string $key): bool
    {
        return $this->context->has($key);
    }

    /**
     * Set a value in the container
     */
    public function set(string $name, mixed $value): static
    {
        $this->context->set($name, $value);
        return $this;
    }

    /**
     * Get a value from the container or return null instead of throwing an exception
     */
    public function getSilently(mixed $key): mixed
    {
        try {
            if ($this->contextHas($key)) {
                return $this->getOrFail($key);
            }
            return null;
        } catch (ContainerExceptionInterface | NotFoundExceptionInterface $e) {
            return null;
        }
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
     * @see PioniaApplication::resolve() for similar functionality on the application instance
     *
     * @param string $key
     * @return mixed
     * @throws ContainerExceptionInterface
     * @throws DependencyException
     * @throws NotFoundException
     * @throws NotFoundExceptionInterface
     */
    public function getOrFail(string $key): mixed
    {
        return $this->context->get($key);
    }

    /**
     * Make an instance of a class from the container. This will throw an exception if the class is not found
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function contextMake(string $name, array $parameters = []): mixed
    {
        return $this->context->make($name, $parameters);
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
     * @param array $dataToAdd
     * @return void
     */
    public function contextArrAdd(string $contextKey, array $dataToAdd): void
    {
        if ($this->contextHas($contextKey)) {
            $data = $this->getSilently($contextKey);
            if (is_a($data, Arrayable::class)) {
                $data->merge($dataToAdd);
                $this->context->set($contextKey, $data);
            } else {
                throw new InvalidArgumentException("The data in the context key $contextKey is not an instance of Arrayable");
            }
        } else {
            $this->context->set($contextKey, $dataToAdd);
        }
    }

}
