<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Performance\BootstrapCacheGenerator;
use Pionia\Performance\OptimizationInstaller;
use Pionia\Performance\PreloadGenerator;
use Pionia\Performance\PreloadManifest;
use Pionia\Performance\PreloadStatsResolver;
use Pionia\Process\Process;
use Pionia\Realm\AppRealm;

/**
 * Optimize the application for production (opt-in scaffold + autoload + OPcache preload).
 */
class OptimizeCommand extends Command
{
    protected string $name = 'optimize';

    protected string $description = 'Production optimization (scaffold, autoload, preload, bootstrap caches)';

    protected string $help = 'Run on deploy. Use --production for the recommended production preset.';

    protected function handle(): int
    {
        $root = $this->appRoot();
        $production = (bool) $this->option('production');
        $settings = PreloadManifest::fromSettings($root);

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

            $settings = PreloadManifest::fromSettings($root);
        }

        $runAutoload = !$this->option('no-autoload');
        $authoritative = $this->option('authoritative')
            || $production
            || $settings['authoritative'];

        if ($runAutoload) {
            $code = $this->dumpAutoload($root, $authoritative);
            if ($code !== 0) {
                return Command::FAILURE;
            }
        }

        $preloadEnabled = $settings['enabled'] && !$this->option('no-preload');
        if ($preloadEnabled) {
            $strategy = $this->resolvePreloadStrategy($root, $settings, $production);
            $generator = new PreloadGenerator($root);
            $result = $generator->generate(strategy: $strategy);

            $this->info('Generated OPcache preload (' . $result['strategy'] . ', ' . $result['files'] . ' app files)');
            $this->line($result['path']);

            if (!$this->validateGeneratedPhp($result['path'])) {
                return Command::FAILURE;
            }
        } elseif ($this->option('no-preload')) {
            $this->line('Skipped OPcache preload generation.');
        } else {
            $this->warn('OPcache preload disabled in [performance] settings.');
        }

        if ($this->shouldGenerateBootstrapCache($root, $production, $settings)) {
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
        $this->printDeployChecklist();

        return Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [
            ['production', 'p', InputOption::VALUE_NONE, 'Production preset: authoritative autoload, bootstrap cache, hybrid preload'],
            ['no-scaffold', null, InputOption::VALUE_NONE, 'Skip installing bootstrap/preload.php and performance settings'],
            ['no-preload', null, InputOption::VALUE_NONE, 'Skip OPcache preload script generation'],
            ['no-autoload', null, InputOption::VALUE_NONE, 'Skip composer dump-autoload -o'],
            ['authoritative', 'a', InputOption::VALUE_NONE, 'Use composer dump-autoload --classmap-authoritative'],
            ['bootstrap-cache', null, InputOption::VALUE_NONE, 'Force bootstrap route/provider caches'],
            ['from-stats', null, InputOption::VALUE_NONE, 'Prefer stats/snapshot preload strategy'],
        ];
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function resolvePreloadStrategy(string $root, array $settings, bool $production): string
    {
        if ($this->option('from-stats')) {
            return 'stats';
        }

        if ($production) {
            $snapshot = PreloadStatsResolver::snapshotPath($root);

            return is_readable($snapshot) ? 'hybrid' : ($settings['strategy'] ?? 'hybrid');
        }

        return (string) ($settings['strategy'] ?? 'hybrid');
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function shouldGenerateBootstrapCache(string $root, bool $production, array $settings): bool
    {
        if ($this->option('bootstrap-cache') || $production) {
            return true;
        }

        return (bool) ($settings['bootstrap_cache'] ?? false)
            || BootstrapCacheGenerator::bootstrapCacheEnabled($root);
    }

    private function printDeployChecklist(): void
    {
        $this->line('');
        $this->line('<comment>Deploy checklist</comment>');
        $this->line('  1. Point php.ini opcache.preload at bootstrap/preload.php');
        $this->line('  2. Set opcache.enable_cli=1 for RoadRunner');
        $this->line('  3. Restart PHP-FPM or RoadRunner workers');
        $this->line('  4. Enable RECORD_OPCACHE_SNAPSHOT=true in staging, then re-run optimize:preload before prod cutover');
    }

    private function appRoot(): string
    {
        if (defined('BASE_PATH')) {
            return (string) BASE_PATH;
        }

        return (string) getcwd();
    }

    private function dumpAutoload(string $root, bool $authoritative): int
    {
        $composer = $root . DIRECTORY_SEPARATOR . 'composer.json';
        if (!is_file($composer)) {
            $this->warn('composer.json not found — skipped autoload optimization.');

            return 0;
        }

        $command = ['composer', 'dump-autoload', '-o'];
        if ($authoritative) {
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

    private function bootApplication(string $root): ?AppRealm
    {
        $bootstrapDir = $root . DIRECTORY_SEPARATOR . 'bootstrap';
        $application = $bootstrapDir . DIRECTORY_SEPARATOR . 'application.php';
        $routes = $bootstrapDir . DIRECTORY_SEPARATOR . 'routes.php';
        $entry = is_file($application) ? $application : (is_file($routes) ? $routes : null);

        if ($entry === null) {
            $this->warn('bootstrap/application.php not found — skipped bootstrap caches.');

            return null;
        }

        try {
            /** @var AppRealm $app */
            $app = require $entry;

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
