<?php

namespace Cache;

use Pionia\Cache\CacheManager;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Cache\PioniaCache;
use Pionia\TestSuite\PioniaTestCase;

class CacheManagerTest extends PioniaTestCase
{
    public function testExtendRegistersCustomAdapter(): void
    {
        $manager = realm()->cache();
        $manager->extend('memory', static fn () => new InMemoryCacheAdapter());

        $adapter = $manager->store('memory');
        $adapter->set('key', 'value');

        $this->assertSame('value', $adapter->get('key'));
    }

    public function testPioniaCacheDelegatesToAdapter(): void
    {
        $adapter = new InMemoryCacheAdapter();
        $cache = new PioniaCache($adapter);
        $cache->set('ping', 'pong');

        $this->assertSame('pong', $cache->get('ping'));
    }
}

final class InMemoryCacheAdapter implements CacheAdapterInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function get($key, $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set($key, $value, $ttl = null): bool
    {
        $this->data[$key] = $value;

        return true;
    }

    public function delete($key): bool
    {
        unset($this->data[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->data = [];

        return true;
    }

    public function getMultiple($keys, $default = null): iterable
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->get($key, $default);
        }

        return $values;
    }

    public function setMultiple($values, $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    public function deleteMultiple($keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has($key): bool
    {
        return array_key_exists($key, $this->data);
    }
}
