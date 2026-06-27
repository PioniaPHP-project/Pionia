<?php

namespace Pionia\Cache\Concerns;

use Pionia\Cache\InvalidCacheArgumentException;

trait ValidatesCacheKeys
{
    private function assertValidKey(mixed $key): void
    {
        if (!is_string($key) || $key === '') {
            throw new InvalidCacheArgumentException('Cache key must be a non-empty string.');
        }

        if (preg_match('#[{}()\/\\\\@:]#', $key)) {
            throw new InvalidCacheArgumentException(sprintf('Cache key "%s" contains reserved characters.', $key));
        }
    }

    /**
     * @param iterable<mixed> $keys
     */
    private function assertValidKeys(iterable $keys): void
    {
        foreach ($keys as $key) {
            $this->assertValidKey($key);
        }
    }
}
