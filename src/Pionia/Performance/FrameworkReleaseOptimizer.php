<?php

namespace Pionia\Performance;

use Pionia\Process\Process;

/**
 * Release-time optimization for the pionia-core package (monorepo root).
 *
 * Writes a portable framework-preload.php into the package tree so it ships on Packagist.
 */
final class FrameworkReleaseOptimizer
{
    public const PACKAGE_PRELOAD_RELATIVE = 'src/Pionia/Resources/optimize/framework-preload.php';

    public function __construct(
        private readonly string $root,
    ) {
    }

    /**
     * @return array{autoload: bool, preload_files: int|null, preload_path: string|null}
     */
    public function optimize(): array
    {
        $autoload = $this->dumpAutoload();

        $preloadFiles = null;
        $preloadPath = null;

        $frameworkSrc = $this->root . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Pionia';
        if (is_dir($frameworkSrc)) {
            $output = $this->root . DIRECTORY_SEPARATOR . self::PACKAGE_PRELOAD_RELATIVE;
            $generator = new PreloadGenerator($this->root);
            $result = $generator->generateFrameworkPackagePreload($frameworkSrc, $output);
            $preloadFiles = $result['files'];
            $preloadPath = $result['path'];
        }

        return [
            'autoload' => $autoload,
            'preload_files' => $preloadFiles,
            'preload_path' => $preloadPath,
        ];
    }

    private function dumpAutoload(): bool
    {
        $composer = $this->root . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($composer)) {
            return false;
        }

        $process = new Process(['composer', 'dump-autoload', '-o'], $this->root, null, null, 120);
        $process->run();

        return $process->isSuccessful();
    }
}
