<?php

namespace Pionia\Cache;

use DIRECTORIES;
use Pionia\Cache\Adapters\FilesystemCacheAdapter;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Collections\Arrayable;
use Pionia\Realm\AppRealm;
use Pionia\Realm\RealmContract;
use Psr\SimpleCache\CacheInterface;

/**
 * Resolves named cache stores and allows developers to register custom adapters.
 */
final class CacheManager
{
    /** @var array<string, CacheAdapterInterface> */
    private array $stores = [];

  /** @var array<string, callable(RealmContract, array<string, mixed>): CacheAdapterInterface> */
    private array $customResolvers = [];

    private ?CacheAdapterInterface $defaultOverride = null;

    public function __construct(private readonly RealmContract $app)
    {
    }

    public function store(?string $name = null): CacheAdapterInterface
    {
        if ($this->defaultOverride !== null && ($name === null || $name === $this->defaultStoreName())) {
            return $this->defaultOverride;
        }

        $name ??= $this->defaultStoreName();

        if (isset($this->stores[$name])) {
            return $this->stores[$name];
        }

        return $this->stores[$name] = $this->buildStore($name);
    }

    /**
     * Register a custom cache store resolver.
     *
     * @param callable(RealmContract, array<string, mixed>): CacheAdapterInterface $resolver
     */
    public function extend(string $name, callable $resolver): static
    {
        $this->customResolvers[$name] = $resolver;
        unset($this->stores[$name]);

        return $this;
    }

    /**
     * Replace the default cache adapter instance directly.
     */
    public function withAdapter(CacheInterface $adapter): static
    {
        if (!$adapter instanceof CacheAdapterInterface) {
            throw new \InvalidArgumentException(
                'Custom cache adapters must implement ' . CacheAdapterInterface::class . '.',
            );
        }

        $this->defaultOverride = $adapter;

        return $this;
    }

    public function defaultStoreName(): string
    {
        $cache = $this->cacheConfig();
        if (!empty($cache['STORE'])) {
            return (string) $cache['STORE'];
        }

        return 'filesystem';
    }

    /**
     * Read cache config without calling env() — avoids circular resolution during PioniaCache boot.
     *
     * @return array<string, mixed>
     */
    private function cacheConfig(): array
    {
        $fromContainer = $this->app->getSilently('cache');
        if (is_array($fromContainer)) {
            return $fromContainer;
        }

        $env = $this->app->getSilently(AppRealm::APP_ENV_TAG);
        if ($env instanceof Arrayable && $env->has('cache')) {
            $cache = $env->get('cache');

            return is_array($cache) ? $cache : [];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function storeConfig(string $name): array
    {
        $stores = $this->configuredStores();
        if ($stores->has($name) && is_array($stores->get($name))) {
            return $stores->get($name);
        }

        return [];
    }

    private function configuredStores(): Arrayable
    {
        $stores = $this->app->getOrDefault('cache.stores', arr([]));

        return $stores instanceof Arrayable ? $stores : arr((array) $stores);
    }

    private function buildStore(string $name): CacheAdapterInterface
    {
        if (isset($this->customResolvers[$name])) {
            return ($this->customResolvers[$name])($this->app, $this->storeConfig($name));
        }

        return match ($name) {
            'filesystem', 'file' => $this->filesystemAdapter($this->storeConfig($name)),
            default => throw new \InvalidArgumentException(sprintf('Unknown cache store "%s". Register it via CacheManager::extend().', $name)),
        };
    }

    /**
     * @param array<string, mixed> $config
     */
    private function filesystemAdapter(array $config): FilesystemCacheAdapter
    {
        $relative = $this->app->getDirFor(DIRECTORIES::CACHE_DIR->name) ?? 'storage/cache';
        $directory = (string) ($config['path'] ?? $this->app->appRoot($relative));
        $ttl = (int) ($config['ttl'] ?? $this->cacheConfig()['TTL'] ?? 0);

        return new FilesystemCacheAdapter($directory, $ttl);
    }
}
