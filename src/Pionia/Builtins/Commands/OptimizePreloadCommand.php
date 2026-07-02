<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Performance\OptimizationInstaller;
use Pionia\Performance\PreloadGenerator;
use Pionia\Performance\PreloadManifest;
use Pionia\Performance\PreloadStatsResolver;
use Pionia\Process\Process;

/**
 * Regenerate the app OPcache preload script from stats or curated rules.
 */
class OptimizePreloadCommand extends Command
{
    protected string $name = 'optimize:preload';

    protected array $aliases = ['preload:optimize'];

    protected string $description = 'Regenerate storage/bootstrap/preload.php (stats, hybrid, or curated)';

    protected string $help = 'Uses storage/metrics/opcache-snapshot.json when present, else live OPcache. Framework manifest is always included.';

    protected function handle(): int
    {
        $root = $this->appRoot();

        if (!$this->option('no-scaffold') && !OptimizationInstaller::isInstalled($root)) {
            (new OptimizationInstaller())->install($root);
            $this->info('Installed production optimization scaffold.');
        }

        if ($this->option('snapshot')) {
            $written = PreloadStatsResolver::writeSnapshot($root);
            if ($written === null) {
                $this->warn('Could not record OPcache snapshot (extension disabled or no scripts cached).');
            } else {
                $this->info('Recorded OPcache snapshot (' . $written['scripts'] . ' scripts).');
                $this->line($written['path']);
            }
        }

        $settings = PreloadManifest::fromSettings($root);
        if (!$settings['enabled'] && !$this->option('force')) {
            $this->error('OPcache preload is disabled. Run `php pionia optimize` or use --force.');

            return Command::FAILURE;
        }

        $strategy = $this->option('strategy');
        if (!is_string($strategy) || $strategy === '') {
            $strategy = $this->option('from-stats') ? 'stats' : $settings['strategy'];
        }

        $generator = new PreloadGenerator($root);
        $result = $generator->generate(strategy: $strategy);

        $this->info('Regenerated preload (' . $result['strategy'] . ', ' . $result['files'] . ' files)');
        $this->line($result['path']);

        if (!$this->validateGeneratedPhp($result['path'])) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [
            ['strategy', null, InputOption::VALUE_OPTIONAL, 'curated, stats, or hybrid'],
            ['from-stats', null, InputOption::VALUE_NONE, 'Use stats/snapshot only (alias: strategy=stats)'],
            ['snapshot', null, InputOption::VALUE_NONE, 'Record OPcache snapshot before generating'],
            ['no-scaffold', null, InputOption::VALUE_NONE, 'Skip installing bootstrap/preload.php'],
            ['force', null, InputOption::VALUE_NONE, 'Generate even when PRELOAD_ENABLED=false'],
        ];
    }

    private function appRoot(): string
    {
        if (defined('BASE_PATH')) {
            return (string) BASE_PATH;
        }

        return (string) getcwd();
    }

    private function validateGeneratedPhp(string $path): bool
    {
        $process = new Process(['php', '-l', $path]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Generated preload script failed syntax check.');

            return false;
        }

        return true;
    }
}
