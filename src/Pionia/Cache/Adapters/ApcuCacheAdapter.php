<?php

namespace Pionia\Cache\Adapters;

use Pionia\Cache\Concerns\ImplementsBulkCacheOperations;
use Pionia\Cache\Concerns\ValidatesCacheKeys;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Cache\InvalidCacheArgumentException;
use Pionia\Cache\Support\CacheTtl;

/**
 * APCu-backed PSR-16 store (ideal for RoadRunner workers on a single host).
 */
final class ApcuCacheAdapter implements CacheAdapterInterface
{
    use ValidatesCacheKeys;
    use ImplementsBulkCacheOperations;

    public function __construct(
        private readonly string $prefix = 'pionia_',
        private readonly int $defaultTtl = 0,
    ) {
        if (!self::isSupported()) {
            throw new InvalidCacheArgumentException(
                'APCu cache requires the apcu extension to be installed and enabled (apc.enabled=1).',
            );
        }
    }

    public static function isSupported(): bool
    {
        return extension_loaded('apcu')
            && (bool) ini_get('apc.enabled')
            && function_exists('apcu_enabled')
            && apcu_enabled();
    }

    public function get($key, $default = null): mixed
    {
        $this->assertValidKey($key);
        $success = false;
        $value = apcu_fetch($this->namespacedKey($key), $success);

        return $success ? $value : $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);
        $seconds = $this->ttlSeconds($ttl);

        if ($seconds === null) {
            return apcu_store($this->namespacedKey((string) $key), $value);
        }

        return apcu_store($this->namespacedKey((string) $key), $value, $seconds);
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);

        return apcu_delete($this->namespacedKey($key));
    }

    public function clear(): bool
    {
        if ($this->prefix === '') {
            return apcu_clear_cache();
        }

        $iterator = new \APCUIterator('/^' . preg_quote($this->prefix, '/') . '/');
        $cleared = true;
        foreach ($iterator as $entry) {
            $cleared = apcu_delete($entry['key']) && $cleared;
        }

        return $cleared;
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);

        return apcu_exists($this->namespacedKey($key));
    }

    private function namespacedKey(string $key): string
    {
        return $this->prefix . $key;
    }

    private function ttlSeconds(null|int|\DateInterval $ttl): ?int
    {
        if ($ttl === null) {
            $ttl = $this->defaultTtl;
        }

        if ($ttl instanceof \DateInterval) {
            $expiresAt = CacheTtl::expiresAt($ttl);

            return $expiresAt === null ? null : max(1, $expiresAt - time());
        }

        if ($ttl <= 0) {
            return null;
        }

        return (int) $ttl;
    }
}
