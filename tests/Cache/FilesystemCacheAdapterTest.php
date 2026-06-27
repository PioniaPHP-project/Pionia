<?php

namespace Cache;

use Pionia\Cache\Adapters\FilesystemCacheAdapter;
use Pionia\TestSuite\PioniaTestCase;

class FilesystemCacheAdapterTest extends PioniaTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pionia_cache_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            (new FilesystemCacheAdapter($this->directory))->clear();
            rmdir($this->directory);
        }

        parent::tearDown();
    }

    public function testSetGetHasDelete(): void
    {
        $cache = new FilesystemCacheAdapter($this->directory, 60);

        $this->assertTrue($cache->set('demo', ['ok' => true], 60));
        $this->assertTrue($cache->has('demo'));
        $this->assertSame(['ok' => true], $cache->get('demo'));
        $this->assertTrue($cache->delete('demo'));
        $this->assertFalse($cache->has('demo'));
    }

    public function testExpiredEntriesArePruned(): void
    {
        $cache = new FilesystemCacheAdapter($this->directory);
        $cache->set('expires-soon', 'value', 1);
        sleep(2);

        $this->assertFalse($cache->has('expires-soon'));
        $this->assertTrue($cache->prune());
        $this->assertNull($cache->get('expires-soon'));
    }
}
