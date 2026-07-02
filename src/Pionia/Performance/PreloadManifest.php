<?php

namespace Pionia\Performance;

/**
 * Curated preload scan rules for production OPcache warming.
 */
final class PreloadManifest
{
    /**
     * Framework paths relative to the application root.
     *
     * @return list<string>
     */
    public static function defaultFrameworkRelativePaths(): array
    {
        return [
            'vendor/pionia/pionia-core/src/Pionia',
        ];
    }

    /**
     * Application code directories relative to the application root.
     *
     * @return list<string>
     */
    public static function defaultApplicationRelativePaths(): array
    {
        return [
            'services',
            'switches',
            'middlewares',
            'providers',
            'commands',
            'authentications',
        ];
    }

    /**
     * Vendor packages (vendor/<package>) to include when present.
     *
     * @return list<string>
     */
    public static function defaultVendorPackages(): array
    {
        return [
            'monolog/monolog',
            'php-di/php-di',
            'nyholm/psr7',
            'spiral/roadrunner-http',
            'spiral/roadrunner-jobs',
            'psr/container',
            'psr/log',
            'psr/cache',
            'psr/simple-cache',
            'psr/event-dispatcher',
        ];
    }

    /**
     * Path fragments skipped anywhere under a scan root.
     *
     * @return list<string>
     */
    public static function defaultExcludeFragments(): array
    {
        return [
            '/tests/',
            '/test/',
            '/TestSuite/',
            '/Builtins/Commands/Generators/',
            '/Resources/scaffolds/',
            '/vendor/bin/',
            '/storage/',
            '/example/',
            '/node_modules/',
            '/frontend/',
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     paths: list<string>,
     *     exclude: list<string>,
     *     bootstrap_cache: bool
     * }
     */
    public static function fromSettings(string $appRoot): array
    {
        $defaults = [
            'enabled' => OptimizationInstaller::isInstalled($appRoot),
            'paths' => [],
            'exclude' => [],
            'bootstrap_cache' => false,
        ];

        $ini = $appRoot . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'settings.ini';
        if (!is_file($ini)) {
            return $defaults;
        }

        $settings = parse_ini_file($ini, true);
        if (!is_array($settings)) {
            return $defaults;
        }

        $section = $settings['performance'] ?? null;
        if (!is_array($section) || $section === []) {
            return $defaults;
        }

        $enabled = self::iniBool($section['PRELOAD_ENABLED'] ?? $section['enabled'] ?? true);
        $bootstrapCache = self::iniBool($section['BOOTSTRAP_CACHE'] ?? $section['bootstrap_cache'] ?? false);

        $paths = self::iniList($section['PRELOAD_PATHS'] ?? $section['preload_paths'] ?? '');
        $exclude = self::iniList($section['PRELOAD_EXCLUDE'] ?? $section['preload_exclude'] ?? '');

        return [
            'enabled' => $enabled,
            'paths' => $paths,
            'exclude' => $exclude,
            'bootstrap_cache' => $bootstrapCache,
        ];
    }

    /**
     * @return list<string>
     */
    public static function resolveScanRoots(string $appRoot, array $settings): array
    {
        $roots = [];

        foreach (self::defaultFrameworkRelativePaths() as $relative) {
            $absolute = $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (is_dir($absolute)) {
                $roots[] = $absolute;
            }
        }

        $extraPaths = $settings['paths'] ?? [];
        if ($extraPaths === []) {
            foreach (self::defaultApplicationRelativePaths() as $relative) {
                $absolute = $appRoot . DIRECTORY_SEPARATOR . $relative;
                if (is_dir($absolute)) {
                    $roots[] = $absolute;
                }
            }
        } else {
            foreach ($extraPaths as $relative) {
                $relative = trim((string) $relative);
                if ($relative === '') {
                    continue;
                }

                $absolute = str_starts_with($relative, DIRECTORY_SEPARATOR)
                    ? $relative
                    : $appRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

                if (is_dir($absolute)) {
                    $roots[] = $absolute;
                }
            }
        }

        foreach (self::defaultVendorPackages() as $package) {
            $absolute = $appRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $package);
            if (is_dir($absolute)) {
                $roots[] = $absolute;
            }
        }

        return array_values(array_unique($roots));
    }

    /**
     * @return list<string>
     */
    public static function resolveExcludeFragments(array $settings): array
    {
        $custom = [];
        foreach ($settings['exclude'] ?? [] as $fragment) {
            $fragment = trim((string) $fragment);
            if ($fragment === '') {
                continue;
            }

            $custom[] = str_contains($fragment, '/')
                ? $fragment
                : '/' . trim($fragment, '/') . '/';
        }

        return array_values(array_unique(array_merge(self::defaultExcludeFragments(), $custom)));
    }

  /**
     * @param array<string, mixed> $section
     */
    private static function iniBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return list<string>
     */
    private static function iniList(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));
    }
}
