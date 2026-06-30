<?php

namespace Pionia\Http\Background;

use Pionia\Http\Moonlight\MoonlightJobQueue;

/**
 * Shared background-work utilities (queue gating, client detach, deferred flush).
 */
final class Background
{
    private static bool $queueFallbackLogged = false;

    public static function assertPromiseSupport(): void
    {
        if (!class_exists(\React\Promise\Deferred::class)) {
            throw new \RuntimeException(
                'async() requires react/promise. Run: composer require react/promise',
            );
        }
    }

    public static function shouldQueue(): bool
    {
        if (!function_exists('moonlightJobsEnabled') || !moonlightJobsEnabled()) {
            return false;
        }

        if (defined('PIONIA_TESTING') && PIONIA_TESTING && !getenv('PIONIA_JOBS_QUEUE')) {
            return false;
        }

        return MoonlightJobQueue::isAvailable();
    }

    /**
     * Release the HTTP client on PHP-FPM before running deferred work.
     */
    public static function finishRequestForClient(): void
    {
        if (\function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    public static function flushDeferredWork(): void
    {
        if (!DeferredWorkBuffer::hasPending()) {
            return;
        }

        try {
            DeferredWorkBuffer::flush();
        } catch (\Throwable $e) {
            if (\function_exists('logger')) {
                logger()->error('Deferred background work flush failed: ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
            }
        }
    }

    public static function logQueueFallback(): void
    {
        if (self::$queueFallbackLogged || !\function_exists('logger')) {
            return;
        }

        self::$queueFallbackLogged = true;
        logger()->warning(
            'Moonlight job queue unavailable, running synchronously after response',
        );
    }

    public static function resetQueueFallbackLog(): void
    {
        self::$queueFallbackLogged = false;
    }
}
