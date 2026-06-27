<?php

namespace Cache;

use Pionia\Cache\Adapters\ArrayCacheAdapter;
use Pionia\Cache\Adapters\NullCacheAdapter;
use Pionia\Cache\CacheManager;
use Pionia\Cache\Contracts\CacheAdapterInterface;
use Pionia\Cache\PioniaCache;
use Pionia\TestSuite\PioniaTestCase;

class CacheManagerTest extends PioniaTestCase
{
    public function testBuiltInArrayAndNullStoresResolve(): void
    {
        $manager = realm()->cache();

        $this->assertInstanceOf(ArrayCacheAdapter::class, $manager->store('array'));
        $this->assertInstanceOf(NullCacheAdapter::class, $manager->store('null'));
    }

    public function testExtendRegistersCustomAdapter(): void
    {
        $manager = realm()->cache();
        $manager->extend('custom', static fn () => new ArrayCacheAdapter());

        $adapter = $manager->store('custom');
        $adapter->set('key', 'value');

        $this->assertSame('value', $adapter->get('key'));
    }

    public function testPioniaCacheDelegatesToAdapter(): void
    {
        $adapter = new ArrayCacheAdapter();
        $cache = new PioniaCache($adapter);
        $cache->set('ping', 'pong');

        $this->assertSame('pong', $cache->get('ping'));
    }

    public function testBuiltinStoreNamesIncludeRegisteredAdapters(): void
    {
        $names = realm()->cache()->registeredStoreNames();

        $this->assertContains('filesystem', $names);
        $this->assertContains('redis', $names);
        $this->assertContains('array', $names);
    }
}
