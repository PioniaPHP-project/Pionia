<?php

namespace Pionia\Performance;

/**
 * Materializes opt-in production optimization files into an application.
 *
 * Files live in src/Pionia/Resources/optimize/ and are copied by `php pionia optimize`.
 */
final class OptimizationInstaller
{
    /**
     * @return list<string> Paths created or updated
     */
    public function install(string $appRoot): array
    {
        $installed = [];
        $stubs = $this->stubsRoot();

        $preloadTarget = $appRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'preload.php';
        $preloadSource = $stubs . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'preload.php';
        if ($this->copyIfMissing($preloadSource, $preloadTarget)) {
            $installed[] = $preloadTarget;
        }

        $iniTarget = $appRoot . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'php.ini.production.example';
        $iniSource = $stubs . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'php.ini.production.example';
        if ($this->copyIfMissing($iniSource, $iniTarget)) {
            $installed[] = $iniTarget;
        }

        $bootstrapDir = $appRoot . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'bootstrap';
        if (!is_dir($bootstrapDir)) {
            mkdir($bootstrapDir, 0775, true);
            $installed[] = $bootstrapDir;
        }

        $gitkeep = $bootstrapDir . DIRECTORY_SEPARATOR . '.gitkeep';
        if (!is_file($gitkeep)) {
            file_put_contents($gitkeep, '');
            $installed[] = $gitkeep;
        }

        if ($this->ensurePerformanceSection($appRoot, $stubs)) {
            $installed[] = $appRoot . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'settings.ini';
        }

        $this->ensureGitignoreRules($appRoot);

        return $installed;
    }

    /**
     * @return list<string> Paths removed
     */
    public function uninstallScaffold(string $appRoot): array
    {
        $removed = [];

        foreach ([
            $appRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'preload.php',
            $appRoot . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'php.ini.production.example',
        ] as $path) {
            if (is_file($path) && @unlink($path)) {
                $removed[] = $path;
            }
        }

        if ($this->removePerformanceSection($appRoot)) {
            $removed[] = $appRoot . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'settings.ini';
        }

        return $removed;
    }

    public static function isInstalled(string $appRoot): bool
    {
        return is_file($appRoot . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'preload.php');
    }

    public function stubsRoot(): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'optimize';
    }

    private function copyIfMissing(string $source, string $target): bool
    {
        if (!is_file($source) || is_file($target)) {
            return false;
        }

        $parent = dirname($target);
        if (!is_dir($parent)) {
            mkdir($parent, 0775, true);
        }

        return copy($source, $target);
    }

    private function ensurePerformanceSection(string $appRoot, string $stubs): bool
    {
        $settingsPath = $appRoot . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'settings.ini';
        if (!is_file($settingsPath)) {
            return false;
        }

        $contents = (string) file_get_contents($settingsPath);
        if (str_contains($contents, '[performance]')) {
            return false;
        }

        $snippetPath = $stubs . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'performance.ini.snippet';
        if (!is_file($snippetPath)) {
            return false;
        }

        $snippet = (string) file_get_contents($snippetPath);
        $contents = rtrim($contents) . PHP_EOL . PHP_EOL . ltrim($snippet);
        file_put_contents($settingsPath, $contents);

        return true;
    }

    private function removePerformanceSection(string $appRoot): bool
    {
        $settingsPath = $appRoot . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'settings.ini';
        if (!is_file($settingsPath)) {
            return false;
        }

        $contents = (string) file_get_contents($settingsPath);
        if (!str_contains($contents, '[performance]')) {
            return false;
        }

        $updated = preg_replace(
            '/\R\[performance\][^\[]*/',
            '',
            $contents,
            1,
        );

        if (!is_string($updated)) {
            return false;
        }

        file_put_contents($settingsPath, rtrim($updated) . PHP_EOL);

        return true;
    }

    private function ensureGitignoreRules(string $appRoot): void
    {
        $gitignore = $appRoot . DIRECTORY_SEPARATOR . '.gitignore';
        if (!is_file($gitignore)) {
            return;
        }

        $contents = (string) file_get_contents($gitignore);
        $rules = [
            '/storage/bootstrap/*',
            '!/storage/bootstrap/.gitkeep',
        ];

        $missing = false;
        foreach ($rules as $rule) {
            if (!str_contains($contents, $rule)) {
                $missing = true;
                break;
            }
        }

        if (!$missing) {
            return;
        }

        $append = '';
        if (!str_contains($contents, '/storage/bootstrap/*')) {
            $append .= "/storage/bootstrap/*\n";
        }
        if (!str_contains($contents, '!/storage/bootstrap/.gitkeep')) {
            $append .= "!/storage/bootstrap/.gitkeep\n";
        }

        if ($append !== '') {
            file_put_contents($gitignore, rtrim($contents) . PHP_EOL . $append);
        }
    }
}
