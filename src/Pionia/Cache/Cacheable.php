<?php

namespace Pionia\Cache;

use Exception;
use Pionia\Utils\Support;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\InvalidArgumentException;

/**
 * Add caching capabilities to any class.
 */
trait Cacheable
{
    /**
     * The cache instance to use for caching.
     * @var ?PioniaCache
     */
    private ?PioniaCache $cacheInstance = null;
    /**
     * The cache prefix to use for caching.
     * @var string
     */
    public string $cachePrefix = __CLASS__;
    /**
     * The cache ttl to use for caching.
     * Defaults to 60 seconds.
     * @var int
     */
    public int $cacheTtl = 60;

    /**
     * Set the cache instance to use for caching.
     * @param ?PioniaCache $cache
     */
    public function setCacheInstance(?PioniaCache $cache): void
    {
        if ($cache === null) {
            $this->cacheInstance = app()->getSilently(PioniaCache::class);
        } else {
            $this->cacheInstance = $cache;
        }
    }

    /**
     * Caches the value if the value is not null.
     * @param string $key The key to cache
     * @param mixed $value The value to cache
     * @param mixed $ttl The time to live for the cache
     * @param bool|null $exact If passed, the key won't be parsed at all, it will be cached as is.
     * @return bool|mixed
     */
    public function cache(string $key, mixed $value = null, mixed $ttl = null, ?bool $exact = false): mixed
    {
        if (!$this->cacheInstance) {
            $this->logger()->warning('Pionia Cache is not active');
            return null;
        }
        if (!$exact) {
            $key = $this->getKeyName($key);
        }
        if ($value === null) {
            return $this->getCache($key, $exact);
        }
        if ($ttl === null) {
            $ttl = $this->cacheTtl;
        }
        return $this->setCache($key, $value, $ttl, $exact);
    }

    public function updateCache($key, $newValue, $exact = false, ?int $ttl =null): void
    {
        if ($this->hasCache($key)){
            $this->deleteCache($key, $exact);
        }
        $this->setCache($key, $newValue, $ttl, $exact);
    }

    /**
     * @param $key
     * @param bool|null $exact
     * @return mixed
     */
    public function getCache($key, ?bool $exact = false): mixed
    {
        try {
            if (!$this->cacheInstance) {
                $this->logger()->warning('Pionia Cache is not active');
                return null;
            }

            if (!$exact) {
                $key = $this->getKeyName($key);
            }
            return $this->cacheInstance->get($key);
        } catch (InvalidArgumentException $e) {
            $this->logger()->error($e->getMessage());
            return null;
        }
    }

    public function setCache(string $key, mixed $value, mixed $ttl = null, ?bool $exact = false): bool
    {
        try {

            if (!$this->cacheInstance) {
                $this->logger()->warning('Pionia Cache is not active');
                // we just ignore caching if the cache is not set.
                return true;
            }
            if (!$exact) {
                $key = $this->getKeyName($key);
            }
            if ($ttl === null) {
                $ttl = $this->cacheTtl;
            }
            return $this->cacheInstance?->set($key, $value, $ttl);
        } catch (InvalidArgumentException $e) {
            $this->logger()->error($e->getMessage());
            return false;
        }
    }

    /**
     * Delete the cache for the key.
     * @param string $key The key to delete
     * @param bool $exact If passed, the key won't be parsed at all
     * @return bool
     */
    public function deleteCache(string $key, ?bool $exact = false): bool
    {
        try {
            if (!$this->cacheInstance) {
                $this->logger()->warning('Pionia Cache is not active');
                return false;
            }
            if (!$exact) {
                $key = $this->getKeyName($key);
            }
            return $this->cacheInstance?->delete($key);
        } catch (InvalidArgumentException $e) {
            $this->logger()->error($e->getMessage());
            return false;
        }
    }

    /**
     * Converts the key to snake case and appends the current class prefix if it does not exist.
     * @param string $key
     * @return string
     */
    private function getKeyName(string $key): string
    {
        $prefix = Support::toSnakeCase(str_ireplace('\\', '_', $this->cachePrefix));
        try {
            if ($this->cachePrefix && !str_starts_with($key, $prefix)) {
                return $prefix .'_'. $key;
            }
            return Support::toSnakeCase($key);
        } catch (Exception $e) {
            $this->logger()->error($e->getMessage());
            return Support::toSnakeCase($key);
        }
    }

    private function cleanCacheKey(string $key): string
    {
        $sr = trim($key);
        $sr = str_ireplace('.', '_', $key);
        $sr = str_ireplace('\\', '_', $key);
        $sr = str_ireplace('/', '_', $sr);
        $sr = str_ireplace(' ', '_', $sr);
        return $sr;
    }

    /**
     * Check if the cache has the key.
     * Will not throw an exception if the key is not set or the cache is not activated.
     * @param $key
     * @param bool|null $exact
     * @return bool
     */
    public function hasCache($key, ?bool $exact = false): bool
    {
        try {
            if (!$this->cacheInstance) {
                return false;
            }
            if (!$exact) {
                $key = $this->getKeyName($key);
            }
            return $this->cacheInstance?->has($key);
        } catch (InvalidArgumentException $e) {
            $this->logger()->error($e->getMessage());
            return false;
        }
    }

    /**
     * Clear the cache.
     * @param array $keys The keys to clear
     * @param bool|null $exact If passed, the keys won't be parsed at all
     * @return bool
     */
    public function ClearByKeys(array $keys, ?bool $exact = false): bool
    {
        if (!$this->cacheInstance) {
            return false;
        }
        foreach ($keys as $key) {
            try {
                $this->deleteCache($key, $exact);
            } catch (Exception $e) {
                $this->logger()->error($e->getMessage());
                return false;
            }
        }
        return true;
    }

    public function getCacheInstance(): ?PioniaCache
    {
        return $this->cacheInstance;
    }

    private function logger(): ?LoggerInterface
    {
        return property_exists($this, 'logger') && $this->logger ? $this->logger : logger();
    }

}
