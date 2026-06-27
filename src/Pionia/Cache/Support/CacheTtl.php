<?php

namespace Pionia\Cache\Support;

final class CacheTtl
{
    public static function expiresAt(null|int|\DateInterval $ttl, int $defaultTtl = 0): ?int
    {
        if ($ttl === null) {
            $ttl = $defaultTtl;
        }

        if ($ttl instanceof \DateInterval) {
            return (new \DateTimeImmutable())->add($ttl)->getTimestamp();
        }

        if ($ttl <= 0) {
            return null;
        }

        return time() + $ttl;
    }

    public static function isExpired(?int $expiresAt): bool
    {
        return $expiresAt !== null && $expiresAt <= time();
    }
}
