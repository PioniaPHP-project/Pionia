<?php

namespace Pionia\Cache\Contracts;

/**
 * Optional contract for adapters that can drop expired entries without clearing everything.
 */
interface PrunableCacheAdapterInterface
{
    public function prune(): bool;
}
