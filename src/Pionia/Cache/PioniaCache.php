<?php

namespace Pionia\Cache;

use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Cache\Contracts\PrunableCacheAdapterInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Application cache facade (PSR-16) backed by a pluggable adapter.
 */
final class PioniaCache implements CacheInterface
{
    public function __construct(
        private readonly CacheAdapterInterface $adapter,
    ) {
    }

    public function adapter(): CacheAdapterInterface
    {
        return $this->adapter;
    }

    public function prune(): bool
    {
        if ($this->adapter instanceof PrunableCacheAdapterInterface) {
            return $this->adapter->prune();
        }

        return false;
    }

    public function get($key, $default = null): mixed
    {
        return $this->adapter->get($key, $default);
    }

    public function set($key, $value, $ttl = null): bool
    {
        return $this->adapter->set($key, $value, $ttl);
    }

    public function delete($key): bool
    {
        return $this->adapter->delete($key);
    }

    public function clear(): bool
    {
        return $this->adapter->clear();
    }

    public function getMultiple($keys, $default = null): iterable
    {
        return $this->adapter->getMultiple($keys, $default);
    }

    public function setMultiple($values, $ttl = null): bool
    {
        return $this->adapter->setMultiple($values, $ttl);
    }

    public function deleteMultiple($keys): bool
    {
        return $this->adapter->deleteMultiple($keys);
    }

    public function has($key): bool
    {
        return $this->adapter->has($key);
    }
}
