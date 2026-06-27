<?php

namespace Pionia\Cache\Adapters;

use Pionia\Cache\Concerns\ImplementsBulkCacheOperations;
use Pionia\Cache\Concerns\ValidatesCacheKeys;
use Pionia\Cache\Contracts\CacheAdapterInterface;

/**
 * No-op PSR-16 store — always misses reads, writes succeed without storing.
 */
final class NullCacheAdapter implements CacheAdapterInterface
{
    use ValidatesCacheKeys;
    use ImplementsBulkCacheOperations;

    public function get($key, $default = null): mixed
    {
        $this->assertValidKey($key);

        return $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);

        return true;
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);

        return true;
    }

    public function clear(): bool
    {
        return true;
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);

        return false;
    }
}
