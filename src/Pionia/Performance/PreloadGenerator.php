<?php

namespace Pionia\Performance;

/**
 * Generates storage/bootstrap/preload.php with opcache_compile_file() calls.
 */
final class PreloadGenerator
{
    public function __construct(
        private readonly string $appRoot,
    ) {
    }

    /**
     * @param list<string>|null $scanRoots When set, skips manifest resolution (framework release).
     * @return array{path: string, files: int, strategy: string}
     */
    public function generate(?string $outputPath = null, ?array $scanRoots = null, ?string $strategy = null): array
    {
        if ($scanRoots !== null) {
            return $this->writeFileList(
                $this->collectFiles($scanRoots, PreloadManifest::defaultExcludeFragments()),
                $outputPath ?? $this->defaultOutputPath(),
                'curated',
                true,
            );
        }

        $settings = PreloadManifest::fromSettings($this->appRoot);
        $strategy ??= $settings['strategy'];

        return match ($strategy) {
            'stats' => $this->generateFromStats($outputPath, $settings),
            'hybrid' => $this->generateHybrid($outputPath, $settings),
            default => $this->generateCurated($outputPath, $settings),
        };
    }

    /**
     * @return array{path: string, files: int, strategy: string}
     */
    public function generateFromStats(?string $outputPath = null, ?array $settings = null): array
    {
        $settings ??= PreloadManifest::fromSettings($this->appRoot);
        $stats = PreloadStatsResolver::resolveHotScripts($this->appRoot);

        if ($stats === []) {
            return $this->generateCurated($outputPath, $settings);
        }

        return $this->writeFileList($stats, $outputPath ?? $this->defaultOutputPath(), 'stats', true);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array{path: string, files: int, strategy: string}
     */
    private function generateCurated(?string $outputPath, array $settings): array
    {
        $scanRoots = PreloadManifest::resolveAppScanRoots($this->appRoot, $settings);
        $excludeFragments = PreloadManifest::resolveExcludeFragments($settings);
        $files = $this->collectFiles($scanRoots, $excludeFragments);

        return $this->writeFileList($files, $outputPath ?? $this->defaultOutputPath(), 'curated', true);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array{path: string, files: int, strategy: string}
     */
    private function generateHybrid(?string $outputPath, array $settings): array
    {
        $stats = PreloadStatsResolver::resolveHotScripts($this->appRoot);
        $excludeFragments = PreloadManifest::resolveExcludeFragments($settings);

        $minimumRoots = [];
        foreach (PreloadManifest::minimumAppRelativePaths() as $relative) {
            $absolute = $this->appRoot . DIRECTORY_SEPARATOR . $relative;
            if (is_dir($absolute)) {
                $minimumRoots[] = $absolute;
            }
        }

        $minimum = $this->collectFiles($minimumRoots, $excludeFragments);
        $files = array_values(array_unique(array_merge($stats, $minimum)));
        sort($files);

        if ($files === []) {
            return $this->generateCurated($outputPath, $settings);
        }

        return $this->writeFileList($files, $outputPath ?? $this->defaultOutputPath(), 'hybrid', true);
    }

    /**
     * @param list<string> $files
     *
     * @return array{path: string, files: int, strategy: string}
     */
    private function writeFileList(array $files, string $target, string $strategy, bool $includeFramework): array
    {
        $directory = dirname($target);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $contents = $this->render($files, $includeFramework, $strategy);
        file_put_contents($target, $contents);

        return [
            'path' => $target,
            'files' => count($files),
            'strategy' => $strategy,
        ];
    }

    /**
     * Portable OPcache manifest shipped inside pionia-core (paths relative to src/Pionia).
     *
     * @return array{path: string, files: int}
     */
    public function generateFrameworkPackagePreload(string $frameworkSrc, string $outputPath): array
    {
        $frameworkSrc = rtrim($frameworkSrc, DIRECTORY_SEPARATOR);
        $files = $this->collectFiles([$frameworkSrc], PreloadManifest::defaultExcludeFragments());

        $relative = [];
        $prefix = $frameworkSrc . DIRECTORY_SEPARATOR;
        foreach ($files as $file) {
            if (str_starts_with($file, $prefix)) {
                $relative[] = substr($file, strlen($prefix));
            }
        }

        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($outputPath, $this->renderFrameworkPackagePreload($relative));

        return [
            'path' => $outputPath,
            'files' => count($relative),
        ];
    }

    public static function frameworkPackagePreloadPath(string $appRoot): ?string
    {
        $candidates = [
            $appRoot . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'pionia' . DIRECTORY_SEPARATOR . 'pionia-core' . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Pionia' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'optimize' . DIRECTORY_SEPARATOR . 'framework-preload.php',
            dirname($appRoot) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Pionia' . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'optimize' . DIRECTORY_SEPARATOR . 'framework-preload.php',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    public function defaultOutputPath(): string
    {
        return $this->appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'preload.php';
    }

    /**
     * @param list<string> $scanRoots
     * @param list<string> $excludeFragments
     *
     * @return list<string>
     */
    private function collectFiles(array $scanRoots, array $excludeFragments): array
    {
        $files = [];

        foreach ($scanRoots as $root) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if (!$file instanceof \SplFileInfo || !$file->isFile()) {
                    continue;
                }

                if (strtolower($file->getExtension()) !== 'php') {
                    continue;
                }

                $path = $file->getPathname();
                if ($this->isExcluded($path, $excludeFragments)) {
                    continue;
                }

                $files[] = $path;
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * @param list<string> $excludeFragments
     */
    private function isExcluded(string $path, array $excludeFragments): bool
    {
        $normalized = str_replace('\\', '/', $path);

        foreach ($excludeFragments as $fragment) {
            if (str_contains($normalized, str_replace('\\', '/', $fragment))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $files
     */
    private function render(array $files, bool $includeFrameworkPackagePreload = true, string $strategy = 'curated'): string
    {
        $lines = [
            '<?php',
            '',
            '/**',
            ' * OPcache preload script — generated by `php pionia optimize`.',
            ' * Strategy: ' . $strategy . '. Loaded via bootstrap/preload.php.',
            ' */',
            '',
            'if (!function_exists(\'opcache_compile_file\')) {',
            '    return;',
            '}',
            '',
        ];

        if ($includeFrameworkPackagePreload) {
            $frameworkPreload = self::frameworkPackagePreloadPath($this->appRoot);
            if ($frameworkPreload !== null) {
                $escaped = addslashes($frameworkPreload);
                $lines[] = "require '{$escaped}';";
                $lines[] = '';
            }
        }

        foreach ($files as $file) {
            $escaped = addslashes($file);
            $lines[] = "opcache_compile_file('{$escaped}');";
        }

        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    /**
     * @param list<string> $relativePaths Paths relative to src/Pionia
     */
    private function renderFrameworkPackagePreload(array $relativePaths): string
    {
        $exported = var_export($relativePaths, true);

        return <<<PHP
<?php

/**
 * OPcache preload manifest for pionia-core — generated at framework release.
 * Portable: resolves paths from this file inside vendor/pionia/pionia-core.
 */
if (!function_exists('opcache_compile_file')) {
    return;
}

\$coreSrc = dirname(__DIR__, 2);
\$files = {$exported};

foreach (\$files as \$relative) {
    \$path = \$coreSrc . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, \$relative);
    if (is_file(\$path)) {
        opcache_compile_file(\$path);
    }
}

PHP;
    }
}
