<?php

namespace Pionia\Performance;

/**
 * Resolves hot PHP files from OPcache statistics or a recorded snapshot.
 */
final class PreloadStatsResolver
{
    public const SNAPSHOT_RELATIVE = 'storage/metrics/opcache-snapshot.json';

    /**
     * @return list<string> Absolute paths to PHP files
     */
    public static function resolveHotScripts(string $appRoot, ?string $snapshotPath = null): array
    {
        $fromSnapshot = self::readSnapshot($snapshotPath ?? self::snapshotPath($appRoot));
        if ($fromSnapshot !== []) {
            return self::filterRelevant($fromSnapshot, $appRoot);
        }

        $live = self::scriptsFromOpcache();
        if ($live !== []) {
            return self::filterRelevant($live, $appRoot);
        }

        return [];
    }

    /**
     * @return array{path: string, scripts: int}|null
     */
    public static function writeSnapshot(string $appRoot): ?array
    {
        $scripts = self::scriptsFromOpcache();
        if ($scripts === []) {
            return null;
        }

        $path = self::snapshotPath($appRoot);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $payload = [
            'recorded_at' => gmdate('c'),
            'scripts' => array_map(static fn (string $file): array => [
                'path' => $file,
            ], $scripts),
        ];

        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return [
            'path' => $path,
            'scripts' => count($scripts),
        ];
    }

    public static function snapshotPath(string $appRoot): string
    {
        return rtrim($appRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::SNAPSHOT_RELATIVE;
    }

    /**
     * @return list<string>
     */
    public static function scriptsFromOpcache(): array
    {
        if (!function_exists('opcache_get_status')) {
            return [];
        }

        $status = @opcache_get_status(true);
        if (!is_array($status) || empty($status['opcache_enabled'])) {
            return [];
        }

        $scripts = $status['scripts'] ?? [];
        if (!is_array($scripts)) {
            return [];
        }

        $paths = [];
        foreach ($scripts as $path => $meta) {
            if (!is_string($path) || !str_ends_with(strtolower($path), '.php')) {
                continue;
            }

            if (!is_file($path)) {
                continue;
            }

            $paths[] = $path;
        }

        sort($paths);

        return array_values(array_unique($paths));
    }

    /**
     * @param list<string> $scripts
     *
     * @return list<string>
     */
    public static function filterRelevant(array $scripts, string $appRoot): array
    {
        $appRoot = rtrim($appRoot, DIRECTORY_SEPARATOR);
        $normalizedRoot = str_replace('\\', '/', $appRoot);

        $allowedFragments = [
            '/vendor/pionia/pionia-core/',
            '/vendor/monolog/',
            '/vendor/php-di/',
            '/vendor/nyholm/',
            '/vendor/spiral/',
            '/services/',
            '/switches/',
            '/middlewares/',
            '/providers/',
            '/commands/',
            '/authentications/',
        ];

        $exclude = PreloadManifest::defaultExcludeFragments();
        $filtered = [];

        foreach ($scripts as $path) {
            $normalized = str_replace('\\', '/', $path);

            if (!str_starts_with($normalized, $normalizedRoot . '/') && !str_contains($normalized, '/vendor/pionia/pionia-core/')) {
                continue;
            }

            $relevant = false;
            foreach ($allowedFragments as $fragment) {
                if (str_contains($normalized, $fragment)) {
                    $relevant = true;
                    break;
                }
            }

            if (!$relevant) {
                continue;
            }

            foreach ($exclude as $fragment) {
                if (str_contains($normalized, str_replace('\\', '/', $fragment))) {
                    $relevant = false;
                    break;
                }
            }

            if ($relevant) {
                $filtered[] = $path;
            }
        }

        sort($filtered);

        return array_values(array_unique($filtered));
    }

    /**
     * @return list<string>
     */
    private static function readSnapshot(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        if (!is_array($data) || !isset($data['scripts']) || !is_array($data['scripts'])) {
            return [];
        }

        $paths = [];
        foreach ($data['scripts'] as $entry) {
            if (is_string($entry) && is_file($entry)) {
                $paths[] = $entry;
            } elseif (is_array($entry) && is_string($entry['path'] ?? null) && is_file($entry['path'])) {
                $paths[] = $entry['path'];
            }
        }

        return $paths;
    }
}
