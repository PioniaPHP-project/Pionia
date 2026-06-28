<?php

namespace Pionia\Builtins\Commands\Frontend;

use Pionia\Builtins\Commands\Concerns\ManagesFrontendSettings;
use Pionia\Console\BaseCommand;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Process\Process;
use Pionia\Utils\Filesystem;

class ScaffoldFrontendCommand extends BaseCommand
{
    use ManagesFrontendSettings;

    protected string $name = 'frontend:scaffold';

    protected array $aliases = ['f:scaffold', 'frontend:s'];

    protected string $description = 'Scaffold a Vite frontend (React, Vue, etc.) in frontend/';

    protected string $help = 'Creates a Vite project, writes [frontend] settings, and configures VITE_API_URL for the Pionia API.';

    /** @var list<string> */
    private array $frameworks = ['react', 'react-ts', 'vue', 'vue-ts', 'svelte', 'svelte-ts'];

    /** @var list<string> */
    private array $packageManagers = ['npm', 'pnpm', 'yarn', 'bun'];

    protected function getOptions(): array
    {
        return [
            ['framework', 'f', InputOption::VALUE_OPTIONAL, 'Vite template (react, vue, react-ts, vue-ts, …)', 'react-ts'],
            ['directory', 'd', InputOption::VALUE_OPTIONAL, 'Frontend root directory name', 'frontend'],
            ['package-manager', 'p', InputOption::VALUE_OPTIONAL, 'Package manager (npm, pnpm, yarn, bun)', 'npm'],
            ['yes', 'y', InputOption::VALUE_NONE, 'Skip prompts and use defaults'],
        ];
    }

    protected function handle(): int
    {
        $fs = new Filesystem();
        $framework = strtolower((string) $this->option('framework'));
        $directory = (string) ($this->option('directory') ?: 'frontend');
        $packageManager = (string) ($this->option('package-manager') ?: 'npm');
        $yes = (bool) $this->option('yes');

        if (!$yes && !in_array($framework, $this->frameworks, true)) {
            $framework = strtolower((string) $this->choice('Choose a Vite template', $this->frameworks, 'react-ts'));
        }

        if (!$yes && !in_array($packageManager, $this->packageManagers, true)) {
            $packageManager = (string) $this->choice('Package manager', $this->packageManagers, 'npm');
        }

        if (str_contains($directory, '/') || str_contains($directory, '\\')) {
            $this->error('Directory name must not contain path separators.');

            return Command::FAILURE;
        }

        $root = app()->appRoot($directory);
        if ($fs->exists($root)) {
            $this->error("Directory already exists: {$directory}");

            return Command::FAILURE;
        }

        $create = $packageManager === 'npm'
            ? 'npm create vite@latest ' . escapeshellarg($directory) . ' -- --template ' . escapeshellarg($framework)
            : escapeshellcmd($packageManager) . ' create vite ' . escapeshellarg($directory) . ' --template ' . escapeshellarg($framework);

        $install = $packageManager === 'npm' ? 'npm install' : escapeshellcmd($packageManager) . ' install';

        $this->info("Scaffolding {$framework} in {$directory}/ …");

        $process = Process::fromShellCommandline($create, app()->appRoot());
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Vite scaffold failed.');

            return Command::FAILURE;
        }

        $this->info('Installing dependencies …');
        $installProcess = Process::fromShellCommandline($install, $root);
        $installProcess->setTimeout(600);
        $installProcess->run();

        if (!$installProcess->isSuccessful()) {
            $this->error(trim($installProcess->getErrorOutput() ?: $installProcess->getOutput()) ?: 'Dependency install failed.');

            return Command::FAILURE;
        }

        $port = $this->resolveApiPort();
        $envDir = is_dir($root . '/src') ? $root . '/src' : $root;
        file_put_contents($envDir . '/.env.development', "VITE_API_URL=http://127.0.0.1:{$port}/api/v1/\n");
        file_put_contents($envDir . '/.env.production', "VITE_API_URL=/api/v1/\n");

        $this->writeViteConfig($root, $port);
        $this->writeApiHelper($root);

        $buildCommand = $packageManager === 'npm' ? 'npm run build' : $packageManager . ' run build';
        $devCommand = $packageManager === 'npm' ? 'npm run dev' : $packageManager . ' run dev';

        $this->writeFrontendSettings([
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

        $this->info('Frontend scaffold complete.');
        $this->line('Dev:  <comment>php pionia frontend:dev</comment>');
        $this->line('Prod: <comment>php pionia frontend:build</comment> then serve from public/');

        return Command::SUCCESS;
    }

    private function writeViteConfig(string $root, int $apiPort): void
    {
        $config = <<<JS
import { defineConfig } from 'vite'

export default defineConfig({
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

        file_put_contents($root . '/vite.config.js', $config);
    }

    private function writeApiHelper(string $root): void
    {
        $srcDir = $root . '/src';
        if (!is_dir($srcDir)) {
            return;
        }

        $helper = <<<'PHP'
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
PHP;

        file_put_contents($srcDir . '/lib/pionia-api.js', $helper);
    }
}
