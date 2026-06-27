<?php

namespace Cache;

use Pionia\Cache\Adapters\NullCacheAdapter;
use Pionia\TestSuite\PioniaTestCase;

class NullCacheAdapterTest extends PioniaTestCase
{
    public function testReadsAlwaysMissAndWritesSucceed(): void
    {
        $cache = new NullCacheAdapter();
        $this->assertFalse($cache->has('missing'));
        $this->assertSame('fallback', $cache->get('missing', 'fallback'));
        $this->assertTrue($cache->set('missing', 'value'));
        $this->assertFalse($cache->has('missing'));
        $this->assertTrue($cache->delete('missing'));
        $this->assertTrue($cache->clear());
    }
}
