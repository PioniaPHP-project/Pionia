<?php

namespace Pionia\Cache\Adapters;

use Pionia\Cache\Concerns\ImplementsBulkCacheOperations;
use Pionia\Cache\Concerns\ValidatesCacheKeys;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Cache\InvalidCacheArgumentException;
use Pionia\Cache\Support\CacheTtl;

/**
 * Redis-backed PSR-16 store (requires ext-redis).
 */
final class RedisCacheAdapter implements CacheAdapterInterface
{
    use ValidatesCacheKeys;
    use ImplementsBulkCacheOperations;

    private readonly \Redis $redis;

    public function __construct(
        private readonly string $prefix = 'pionia:',
        private readonly int $defaultTtl = 0,
        string $host = '127.0.0.1',
        int $port = 6379,
        ?string $password = null,
        int $database = 0,
        float $timeout = 1.5,
    ) {
        if (!extension_loaded('redis')) {
            throw new InvalidCacheArgumentException(
                'Redis cache requires the redis PHP extension. Install ext-redis or choose another store.',
            );
        }

        $this->redis = new \Redis();
        if (!$this->redis->connect($host, $port, $timeout)) {
            throw new InvalidCacheArgumentException(sprintf('Unable to connect to Redis at %s:%d.', $host, $port));
        }

        if ($password !== null && $password !== '') {
            $this->redis->auth($password);
        }

        if ($database > 0) {
            $this->redis->select($database);
        }
    }

    public function get($key, $default = null): mixed
    {
        $this->assertValidKey($key);
        $raw = $this->redis->get($this->namespacedKey($key));
        if ($raw === false) {
            return $default;
        }

        try {
            return unserialize((string) $raw, ['allowed_classes' => true]);
        } catch (\Throwable) {
            $this->delete($key);

            return $default;
        }
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);
        $payload = serialize($value);
        $seconds = $this->ttlSeconds($ttl);

        if ($seconds === null) {
            return $this->redis->set($this->namespacedKey($key), $payload);
        }

        return $this->redis->setex($this->namespacedKey($key), $seconds, $payload);
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);

        return $this->redis->del($this->namespacedKey($key)) >= 0;
    }

    public function clear(): bool
    {
        if ($this->prefix === '') {
            return $this->redis->flushDB();
        }

        $iterator = null;
        do {
            $keys = $this->redis->scan($iterator, $this->prefix . '*', 100);
            if ($keys !== false && $keys !== []) {
                $this->redis->del($keys);
            }
        } while ($iterator !== 0 && $iterator !== false);

        return true;
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);

        return (bool) $this->redis->exists($this->namespacedKey($key));
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

        return $ttl;
    }
}
