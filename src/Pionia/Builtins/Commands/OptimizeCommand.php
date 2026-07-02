<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Performance\BootstrapCacheGenerator;
use Pionia\Performance\OptimizationInstaller;
use Pionia\Performance\PreloadGenerator;
use Pionia\Performance\PreloadManifest;
use Pionia\Process\Process;
use Pionia\Realm\AppRealm;

/**
 * Optimize the application for production (opt-in scaffold + autoload + OPcache preload).
 */
class OptimizeCommand extends Command
{
    protected string $name = 'optimize';

    protected string $description = 'Opt in to production performance (scaffold files, autoload, OPcache preload)';

    protected string $help = 'Installs bootstrap/preload.php and related config, then generates optimization artifacts. Run on deploy.';

    protected function handle(): int
    {
        $root = $this->appRoot();

        if (!$this->option('no-scaffold')) {
            $installer = new OptimizationInstaller();
            $installed = $installer->install($root);

            if ($installed !== []) {
                $this->info('Installed production optimization files:');
                foreach ($installed as $path) {
                    $this->line('  ' . $path);
                }
            } else {
                $this->line('Production optimization files already present.');
            }
        }

        if (!$this->option('no-autoload')) {
            $code = $this->dumpAutoload($root);
            if ($code !== 0) {
                return Command::FAILURE;
            }
        }

        if (!$this->option('no-preload') && PreloadManifest::fromSettings($root)['enabled']) {
            $generator = new PreloadGenerator($root);
            $result = $generator->generate();
            $this->info('Generated OPcache preload script (' . $result['files'] . ' files)');
            $this->line($result['path']);

            if (!$this->validateGeneratedPhp($result['path'])) {
                return Command::FAILURE;
            }
        } elseif ($this->option('no-preload')) {
            $this->line('Skipped OPcache preload generation.');
        } else {
            $this->warn('OPcache preload disabled. Run without --no-scaffold or enable [performance] PRELOAD_ENABLED=true.');
        }

        if ($this->shouldGenerateBootstrapCache($root)) {
            $app = $this->bootApplication($root);
            if ($app instanceof AppRealm) {
                $bootstrap = new BootstrapCacheGenerator($root);
                $artifacts = $bootstrap->generate($app);

                if ($artifacts['routes'] !== null) {
                    $this->info('Cached routes: ' . $artifacts['routes']);
                }

                if ($artifacts['providers'] !== null) {
                    $this->info('Cached providers: ' . $artifacts['providers']);
                }
            }
        }

        $this->info('Optimization complete.');
        $this->line('Point php.ini opcache.preload at bootstrap/preload.php and restart PHP-FPM or RoadRunner workers.');

        return Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [
            ['no-scaffold', null, InputOption::VALUE_NONE, 'Skip installing bootstrap/preload.php and performance settings'],
            ['no-preload', null, InputOption::VALUE_NONE, 'Skip OPcache preload script generation'],
            ['no-autoload', null, InputOption::VALUE_NONE, 'Skip composer dump-autoload -o'],
            ['authoritative', 'a', InputOption::VALUE_NONE, 'Use composer dump-autoload --classmap-authoritative'],
            ['bootstrap-cache', null, InputOption::VALUE_NONE, 'Force bootstrap route/provider caches'],
        ];
    }

    private function appRoot(): string
    {
        if (defined('BASE_PATH')) {
            return (string) BASE_PATH;
        }

        return (string) getcwd();
    }

    private function dumpAutoload(string $root): int
    {
        $composer = $root . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($composer)) {
            $this->warn('composer.json not found — skipped autoload optimization.');

            return 0;
        }

        $command = ['composer', 'dump-autoload', '-o'];
        if ($this->option('authoritative')) {
            $command[] = '--classmap-authoritative';
        }

        $this->info('Optimizing Composer autoload class map...');
        $process = new Process($command, $root, null, null, 120);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->error('composer dump-autoload failed.');

            return 1;
        }

        return 0;
    }

    private function shouldGenerateBootstrapCache(string $root): bool
    {
        if ($this->option('bootstrap-cache')) {
            return true;
        }

        return PreloadManifest::fromSettings($root)['bootstrap_cache']
            || BootstrapCacheGenerator::bootstrapCacheEnabled($root);
    }

    private function bootApplication(string $root): ?AppRealm
    {
        $routes = $root . DIRECTORY_SEPARATOR . 'bootstrap' . DIRECTORY_SEPARATOR . 'routes.php';
        if (!is_file($routes)) {
            $this->warn('bootstrap/routes.php not found — skipped bootstrap caches.');

            return null;
        }

        try {
            /** @var AppRealm $app */
            $app = require $routes;

            return $app instanceof AppRealm ? $app : null;
        } catch (\Throwable $e) {
            $this->warn('Could not boot application for bootstrap caches: ' . $e->getMessage());

            return null;
        }
    }

    private function validateGeneratedPhp(string $path): bool
    {
        $process = new Process(['php', '-l', $path]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Generated preload script failed syntax check.');
            $this->line(trim($process->getErrorOutput() ?: $process->getOutput()));

            return false;
        }

        return true;
    }
}
