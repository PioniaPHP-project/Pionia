<?php

namespace Pionia\Builtins\Commands\Concerns;

trait ManagesFrontendSettings
{
    /**
     * @return array<string, mixed>
     */
    private function frontendSettings(): array
    {
        $config = frontendConfig();

        return is_array($config) ? $config : [];
    }

    private function frontendRoot(): string
    {
        $root = (string) ($this->frontendSettings()['ROOT'] ?? $this->frontendSettings()['root'] ?? 'frontend');

        return app()->appRoot($root);
    }

    private function frontendBuildOutputDir(): string
    {
        $output = (string) ($this->frontendSettings()['OUTPUT_DIR'] ?? $this->frontendSettings()['output_dir'] ?? 'dist');

        return $this->frontendRoot() . DIRECTORY_SEPARATOR . $output;
    }

    private function frontendDeployDir(): string
    {
        $deploy = (string) ($this->frontendSettings()['DEPLOY_TO'] ?? $this->frontendSettings()['deploy_to'] ?? 'public');

        return app()->appRoot($deploy);
    }

    private function frontendBuildCommand(): string
    {
        return (string) ($this->frontendSettings()['BUILD_COMMAND'] ?? $this->frontendSettings()['build_command'] ?? 'npm run build');
    }

    private function frontendPackageManager(): string
    {
        return (string) ($this->frontendSettings()['PACKAGE_MANAGER'] ?? $this->frontendSettings()['package_manager'] ?? 'npm');
    }

    private function frontendDevCommand(): string
    {
        $pm = $this->frontendPackageManager();
        $cmd = (string) ($this->frontendSettings()['DEV_COMMAND'] ?? $this->frontendSettings()['dev_command'] ?? '');

        if ($cmd !== '') {
            return $cmd;
        }

        return $pm === 'npm' ? 'npm run dev' : $pm . ' dev';
    }

    private function frontendDevPort(): int
    {
        $port = $this->frontendSettings()['DEV_PORT'] ?? $this->frontendSettings()['dev_port'] ?? 5173;

        return max(1, (int) $port);
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function writeFrontendSettings(array $settings): bool
    {
        $path = app()->envPath('settings.ini');
        $config = is_file($path) ? (parse_ini_file($path, true) ?: []) : [];
        $config['frontend'] = array_merge($config['frontend'] ?? [], $settings);

        if (!writeIniFile($path, $config)) {
            return false;
        }

        clearstatcache(true, $path);

        return true;
    }

    private function resolveApiPort(): int
    {
        $port = env('PORT') ?? env('SERVER_PORT') ?? 8003;

        return max(1, (int) $port);
    }

    private function mirrorDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            throw new \RuntimeException("Build output directory not found: {$source}");
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0775, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $target = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathname();

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    mkdir($target, 0775, true);
                }

                continue;
            }

            $parent = dirname($target);
            if (!is_dir($parent)) {
                mkdir($parent, 0775, true);
            }

            copy($item->getPathname(), $target);
        }
    }
}
