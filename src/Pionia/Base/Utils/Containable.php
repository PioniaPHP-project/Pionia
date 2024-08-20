<?php

namespace Pionia\Pionia\Base\Utils;

use DI\DependencyException;
use DI\NotFoundException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

trait Containable
{
    /**
     * Application container context
     * @var ?ContainerInterface
     */
    public ?ContainerInterface $context;

    public function has(string $key): bool
    {
        return $this->context->has($key);
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
