<?php

namespace Cache;

use Pionia\Cache\Adapters\ArrayCacheAdapter;
use Pionia\TestSuite\PioniaTestCase;

class ArrayCacheAdapterTest extends PioniaTestCase
{
    public function testSetGetHasDeleteAndExpiry(): void
    {
        $cache = new ArrayCacheAdapter(60);
        $cache->set('demo', ['ok' => true], 60);
        $this->assertTrue($cache->has('demo'));
        $this->assertSame(['ok' => true], $cache->get('demo'));
        $this->assertTrue($cache->delete('demo'));
        $this->assertFalse($cache->has('demo'));
    }

    public function testPruneRemovesExpiredEntries(): void
    {
        $cache = new ArrayCacheAdapter();
        $cache->set('expires-soon', 'value', 1);
        sleep(2);

        $this->assertTrue($cache->prune());
        $this->assertNull($cache->get('expires-soon'));
    }
}
