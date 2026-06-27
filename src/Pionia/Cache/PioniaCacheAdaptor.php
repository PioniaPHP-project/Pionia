<?php

namespace Pionia\Cache;

use Pionia\Cache\Adapters\FilesystemCacheAdapter;
use Pionia\Cache\Contracts\CacheAdapterInterface;

/**
 * @deprecated Use {@see FilesystemCacheAdapter} or register a store via {@see CacheManager::extend()}.
 */
class PioniaCacheAdaptor extends FilesystemCacheAdapter implements CacheAdapterInterface
{
}
