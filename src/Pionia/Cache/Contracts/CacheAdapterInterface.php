<?php

namespace Pionia\Cache\Contracts;

use Psr\SimpleCache\CacheInterface;

/**
 * Pionia cache store contract (PSR-16).
 *
 * Implement this interface to register a custom cache backend via
 * {@see \Pionia\Cache\CacheManager::extend()} or {@see \Pionia\Realm\AppRealm::withCacheAdaptor()}.
 *
 * Built-in implementations live in {@see \Pionia\Cache\Adapters}. Reuse
 * {@see \Pionia\Cache\Concerns\ValidatesCacheKeys} and
 * {@see \Pionia\Cache\Concerns\ImplementsBulkCacheOperations} when authoring new adapters.
 */
interface CacheAdapterInterface extends CacheInterface
{
}
