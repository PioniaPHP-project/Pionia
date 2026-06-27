<?php

namespace Pionia\Cache\Contracts;

use Psr\SimpleCache\CacheInterface;

/**
 * Pionia cache store contract (PSR-16).
 *
 * Implement this interface to register a custom cache backend via
 * {@see \Pionia\Cache\CacheManager::extend()} or {@see \Pionia\Realm\AppRealm::withCacheAdaptor()}.
 */
interface CacheAdapterInterface extends CacheInterface
{
}
