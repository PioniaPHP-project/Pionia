<?php

namespace Pionia\Scaffolding;

use Pionia\Http\Server\ServerPortResolver;
use Pionia\Process\Process;
use Pionia\Utils\Filesystem;

/**
 * Scaffolds a Vite frontend and writes [frontend] settings for a Pionia app.
 */
final class FrontendScaffolder
{
    /** @var list<string> */
    private array $packageManagers = ['npm', 'pnpm', 'yarn', 'bun'];

    public function __construct(
        private readonly string $projectRoot,
        private $output = null,
    ) {
    }

    public function scaffold(
        string $framework,
        string $directory = 'frontend',
        string $packageManager = 'npm',
    ): void {
        $framework = strtolower($framework);
        if (!FrontendFramework::isValid($framework)) {
            throw new \InvalidArgumentException("Unsupported Vite template: {$framework}");
        }

        if (!in_array($packageManager, $this->packageManagers, true)) {
            throw new \InvalidArgumentException("Unsupported package manager: {$packageManager}");
        }

        if (str_contains($directory, '/') || str_contains($directory, '\\')) {
            throw new \InvalidArgumentException('Directory name must not contain path separators.');
        }

        $fs = new Filesystem();
        $root = rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $directory;
        if ($fs->exists($root)) {
            throw new \RuntimeException("Directory already exists: {$directory}");
        }

        $create = $packageManager === 'npm'
            ? 'npm create vite@latest ' . escapeshellarg($directory) . ' -- --template ' . escapeshellarg($framework)
            : escapeshellcmd($packageManager) . ' create vite ' . escapeshellarg($directory) . ' --template ' . escapeshellarg($framework);

        $install = $packageManager === 'npm' ? 'npm install' : escapeshellcmd($packageManager) . ' install';

        $this->line("Scaffolding {$framework} in {$directory}/ …");

        $process = Process::fromShellCommandline($create, $this->projectRoot);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Vite scaffold failed.');
        }

        $this->line('Installing dependencies …');
        $installProcess = Process::fromShellCommandline($install, $root);
        $installProcess->setTimeout(600);
        $installProcess->run();

        if (!$installProcess->isSuccessful()) {
            throw new \RuntimeException(trim($installProcess->getErrorOutput() ?: $installProcess->getOutput()) ?: 'Dependency install failed.');
        }

        $port = (new ServerPortResolver())->resolve();
        $envDir = is_dir($root . '/src') ? $root . '/src' : $root;
        file_put_contents($envDir . '/.env.development', "VITE_API_URL=http://127.0.0.1:{$port}/api/v1/\n");
        file_put_contents($envDir . '/.env.production', "VITE_API_URL=/api/v1/\n");

        $this->writeViteConfig($root, $port, $framework);
        $this->writeApiHelper($root);
        $this->writeFrontendSettings($directory, $framework, $packageManager, $port);
    }

  /**
   * @param array<string, mixed> $settings
   */
    private function writeFrontendSettings(string $directory, string $framework, string $packageManager, int $port): void
    {
        $buildCommand = $packageManager === 'npm' ? 'npm run build' : $packageManager . ' run build';
        $devCommand = $packageManager === 'npm' ? 'npm run dev' : $packageManager . ' run dev';

        $path = rtrim($this->projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . 'settings.ini';
        $config = is_file($path) ? (parse_ini_file($path, true) ?: []) : [];
        $config['frontend'] = array_merge($config['frontend'] ?? [], [
            'ROOT' => $directory,
            'FRAMEWORK' => $framework,
            'PACKAGE_MANAGER' => $packageManager,
            'OUTPUT_DIR' => 'dist',
            'DEPLOY_TO' => 'public',
            'BUILD_COMMAND' => $buildCommand,
            'DEV_COMMAND' => $devCommand,
            'DEV_PORT' => '5173',
            'SPA_FALLBACK' => 'true',
            'API_BASE_DEV' => "http://127.0.0.1:{$port}/api/v1/",
            'API_BASE_PROD' => '/api/v1/',
        ]);

        if (!writeIniFile($path, $config)) {
            throw new \RuntimeException('Failed to write [frontend] settings.');
        }

        clearstatcache(true, $path);
    }

    private function writeViteConfig(string $root, int $apiPort, string $framework): void
    {
        $path = $this->resolveViteConfigPath($root);
        $isTs = str_ends_with($path, '.ts') || str_ends_with($path, '.mts');
        $plugin = $this->vitePluginBlock($framework);

        $config = $plugin . <<<JS

export default defineConfig({
  plugins: [{$this->vitePluginCall($framework)}],
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:{$apiPort}',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: 'dist',
    emptyOutDir: true,
  },
})
JS;

        file_put_contents($path, $config);

        $duplicate = $root . '/vite.config.' . ($isTs ? 'js' : 'ts');
        if ($duplicate !== $path && is_file($duplicate)) {
            unlink($duplicate);
        }
    }

    private function resolveViteConfigPath(string $root): string
    {
        foreach (['vite.config.ts', 'vite.config.mts', 'vite.config.js', 'vite.config.mjs'] as $name) {
            $path = $root . '/' . $name;
            if (is_file($path)) {
                return $path;
            }
        }

        return $root . '/vite.config.js';
    }

    private function vitePluginBlock(string $framework): string
    {
        return match (true) {
            str_starts_with($framework, 'react') => "import react from '@vitejs/plugin-react'\n",
            str_starts_with($framework, 'vue') => "import vue from '@vitejs/plugin-vue'\n",
            str_starts_with($framework, 'svelte') => "import { svelte } from '@sveltejs/vite-plugin-svelte'\n",
            default => '',
        } . "import { defineConfig } from 'vite'\n";
    }

    private function vitePluginCall(string $framework): string
    {
        return match (true) {
            str_starts_with($framework, 'react') => 'react()',
            str_starts_with($framework, 'vue') => 'vue()',
            str_starts_with($framework, 'svelte') => 'svelte()',
            default => '',
        };
    }

    private function writeApiHelper(string $root): void
    {
        $srcDir = $root . '/src';
        if (!is_dir($srcDir)) {
            return;
        }

        $helper = <<<'JS'
/**
 * Minimal Moonlight API client for Vite apps.
 */
export async function callAction(service, action, payload = {}) {
  const base = import.meta.env.VITE_API_URL ?? '/api/v1/';
  const url = base.endsWith('/') ? base : `${base}/`;

  const response = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ service, action, ...payload }),
  });

  return response.json();
}
JS;

        $libDir = $srcDir . '/lib';
        (new Filesystem())->mkdir($libDir);
        file_put_contents($libDir . '/pionia-api.js', $helper);
    }

    private function line(string $message): void
    {
        if ($this->output !== null) {
            ($this->output)($message);
        }
    }
}
