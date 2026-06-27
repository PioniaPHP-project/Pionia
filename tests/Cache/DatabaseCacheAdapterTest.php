<?php

namespace Cache;

use Pionia\Cache\Adapters\DatabaseCacheAdapter;
use Pionia\Porm\ConnectionManager;
use Pionia\TestSuite\Concerns\UsesInMemoryDatabase;
use Pionia\TestSuite\PioniaTestCase;

class DatabaseCacheAdapterTest extends PioniaTestCase
{
    use UsesInMemoryDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->useInMemoryDatabase();
        realm()->get(ConnectionManager::class)->register('default', $connection);
    }

    public function testPersistsValuesInDatabase(): void
    {
        $connections = realm()->get(ConnectionManager::class);
        $cache = new DatabaseCacheAdapter($connections, 'cache', 60, 'default');

        $this->assertTrue($cache->set('user_count', 42, 60));
        $this->assertSame(42, $cache->get('user_count'));
        $this->assertTrue($cache->has('user_count'));
        $this->assertTrue($cache->delete('user_count'));
        $this->assertFalse($cache->has('user_count'));
    }

    public function testPruneRemovesExpiredRows(): void
    {
        $connections = realm()->get(ConnectionManager::class);
        $cache = new DatabaseCacheAdapter($connections, 'cache', 0, 'default');
        $cache->set('expires-soon', 'value', 1);
        sleep(2);

        $this->assertTrue($cache->prune());
        $this->assertNull($cache->get('expires-soon'));
    }
}
