<?php

namespace Pionia\Performance;

/**
 * Periodically records OPcache script lists from running workers for stats-driven preload.
 */
final class OpcacheSnapshotRecorder
{
    private const int MIN_INTERVAL_SECONDS = 300;

    public static function maybeRecord(string $appRoot): void
    {
        if (!PreloadManifest::shouldRecordOpcacheSnapshot($appRoot)) {
            return;
        }

        $path = PreloadStatsResolver::snapshotPath($appRoot);
        if (is_file($path) && (time() - (int) filemtime($path)) < self::MIN_INTERVAL_SECONDS) {
            return;
        }

        PreloadStatsResolver::writeSnapshot($appRoot);
    }
}
