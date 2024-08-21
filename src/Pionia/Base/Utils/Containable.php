<?php

namespace Pionia\Pionia\Base\Utils;

use DI\Container;
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

    public function has(string $key): bool
    {
        return $this->context->has($key);
    }

    /**
     * Set a value in the container
     */
    public function set(string $name, mixed $value): void
    {
        $this->context->set($name, $value);
    }

    public function getSilently(mixed $key): mixed
    {
        try {
            if ($this->has($key)) {
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
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function getOrFail(mixed $key): mixed
    {
        return $this->context->get($key);
    }
}
