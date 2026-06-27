<?php

namespace Pionia\Cache;

use DIRECTORIES;
use Pionia\Cache\Adapters\ApcuCacheAdapter;
use Pionia\Cache\Adapters\ArrayCacheAdapter;
use Pionia\Cache\Adapters\DatabaseCacheAdapter;
use Pionia\Cache\Adapters\FilesystemCacheAdapter;
use Pionia\Cache\Adapters\NullCacheAdapter;
use Pionia\Cache\Adapters\RedisCacheAdapter;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Collections\Arrayable;
use Pionia\Porm\ConnectionManager;
use Pionia\Realm\AppRealm;
use Pionia\Realm\RealmContract;
use Psr\SimpleCache\CacheInterface;

/**
 * Resolves named cache stores and allows developers to register custom adapters.
 *
 * Built-in stores: filesystem, array, null, database, apcu, redis.
 * Configure via `[cache]` in settings.ini or register custom stores with {@see extend()}.
 */
final class CacheManager
{
    /** @var list<string> */
    public const BUILTIN_STORES = [
        'filesystem',
        'file',
        'array',
        'memory',
        'null',
        'void',
        'database',
        'db',
        'apcu',
        'redis',
    ];

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
     * @return list<string>
     */
    public function registeredStoreNames(): array
    {
        return array_values(array_unique([
            ...self::BUILTIN_STORES,
            ...array_keys($this->customResolvers),
        ]));
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

        $env = $this->app->getSilently(AppRealm::APP_ENV_TAG);
        if ($env instanceof Arrayable) {
            foreach ([
                'cache_' . $name,
                'cache.stores.' . $name,
            ] as $section) {
                if ($env->has($section) && is_array($env->get($section))) {
                    return $env->get($section);
                }
            }
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

        $config = $this->storeConfig($name);
        $defaultTtl = (int) ($config['ttl'] ?? $this->cacheConfig()['TTL'] ?? 0);

        return match ($name) {
            'filesystem', 'file' => $this->filesystemAdapter($config),
            'array', 'memory' => new ArrayCacheAdapter($defaultTtl),
            'null', 'void' => new NullCacheAdapter(),
            'database', 'db' => $this->databaseAdapter($config, $defaultTtl),
            'apcu' => $this->apcuAdapter($config, $defaultTtl),
            'redis' => $this->redisAdapter($config, $defaultTtl),
            default => throw new \InvalidArgumentException(sprintf(
                'Unknown cache store "%s". Built-in stores: %s. Register custom stores via CacheManager::extend().',
                $name,
                implode(', ', self::BUILTIN_STORES),
            )),
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

    /**
     * @param array<string, mixed> $config
     */
    private function databaseAdapter(array $config, int $defaultTtl): DatabaseCacheAdapter
    {
        return new DatabaseCacheAdapter(
            $this->app->get(ConnectionManager::class),
            (string) ($config['table'] ?? 'cache'),
            $defaultTtl,
            isset($config['connection']) ? (string) $config['connection'] : 'default',
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function apcuAdapter(array $config, int $defaultTtl): ApcuCacheAdapter
    {
        return new ApcuCacheAdapter(
            (string) ($config['prefix'] ?? 'pionia_'),
            $defaultTtl,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function redisAdapter(array $config, int $defaultTtl): RedisCacheAdapter
    {
        return new RedisCacheAdapter(
            (string) ($config['prefix'] ?? 'pionia:'),
            $defaultTtl,
            (string) ($config['host'] ?? '127.0.0.1'),
            (int) ($config['port'] ?? 6379),
            isset($config['password']) ? (string) $config['password'] : null,
            (int) ($config['database'] ?? 0),
            (float) ($config['timeout'] ?? 1.5),
        );
    }
}
