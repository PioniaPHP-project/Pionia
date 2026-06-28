<?php

namespace Pionia\Realtime;

/**
 * Realtime connection helpers for Centrifugo (channel naming, config).
 *
 * Connection JWT signing is delegated to Centrifugo; this class provides
 * Pionia-side channel conventions and settings access.
 */
final class RealtimeGateway
{
    /**
     * Default channel prefix for a Moonlight service alias.
     */
    public static function serviceChannel(string $service): string
    {
        $prefix = self::channelPrefix();

        return rtrim($prefix, ':') . ':' . $service;
    }

    public static function channelPrefix(): string
    {
        $realtime = realtimeConfig();

        foreach (['CHANNEL_PREFIX', 'channel_prefix'] as $key) {
            if (!empty($realtime[$key]) && is_string($realtime[$key])) {
                return $realtime[$key];
            }
        }

        return 'moonlight';
    }

    public static function enabled(): bool
    {
        $realtime = realtimeConfig();

        foreach (['ENABLED', 'enabled'] as $key) {
            if (array_key_exists($key, $realtime)) {
                return filter_var($realtime[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return filter_var(env('REALTIME_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }
}
