<?php

namespace Pionia\Cache\Adapters;

use Pionia\Cache\Concerns\ImplementsBulkCacheOperations;
use Pionia\Cache\Concerns\ValidatesCacheKeys;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Cache\Contracts\PrunableCacheAdapterInterface;
use Pionia\Cache\Support\CacheTtl;

/**
 * In-process PSR-16 store (request/worker lifetime).
 */
final class ArrayCacheAdapter implements CacheAdapterInterface, PrunableCacheAdapterInterface
{
    use ValidatesCacheKeys;
    use ImplementsBulkCacheOperations;

    /** @var array<string, array{value: mixed, expires: int|null}> */
    private array $entries = [];

    public function __construct(private readonly int $defaultTtl = 0)
    {
    }

    public function get($key, $default = null): mixed
    {
        $this->assertValidKey($key);
        $entry = $this->entries[(string) $key] ?? null;
        if ($entry === null || CacheTtl::isExpired($entry['expires'])) {
            unset($this->entries[(string) $key]);

            return $default;
        }

        return $entry['value'];
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);
        $this->entries[(string) $key] = [
            'value' => $value,
            'expires' => CacheTtl::expiresAt($ttl, $this->defaultTtl),
        ];

        return true;
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);
        unset($this->entries[(string) $key]);

        return true;
    }

    public function clear(): bool
    {
        $this->entries = [];

        return true;
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);
        $entry = $this->entries[(string) $key] ?? null;
        if ($entry === null || CacheTtl::isExpired($entry['expires'])) {
            unset($this->entries[(string) $key]);

            return false;
        }

        return true;
    }

    public function prune(): bool
    {
        $pruned = false;
        foreach (array_keys($this->entries) as $key) {
            $expires = $this->entries[$key]['expires'] ?? null;
            if (CacheTtl::isExpired($expires)) {
                unset($this->entries[$key]);
                $pruned = true;
            }
        }

        return $pruned;
    }
}
