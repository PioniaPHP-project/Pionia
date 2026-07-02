<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Performance\BootstrapCacheGenerator;
use Pionia\Performance\OptimizationInstaller;

/**
 * Remove generated optimization artifacts.
 */
class OptimizeClearCommand extends Command
{
    protected string $name = 'optimize:clear';

    protected array $aliases = ['clear-optimize'];

    protected string $description = 'Remove generated optimization artifacts (preload and bootstrap caches)';

    protected function handle(): int
    {
        $root = $this->appRoot();
        $removed = 0;

        foreach (BootstrapCacheGenerator::artifactPaths($root) as $path) {
            if (!is_file($path)) {
                continue;
            }

            if (@unlink($path)) {
                $removed++;
                $this->line('Removed ' . $path);
            }
        }

        if ($this->option('scaffold')) {
            $installer = new OptimizationInstaller();
            foreach ($installer->uninstallScaffold($root) as $path) {
                $this->line('Removed scaffold ' . $path);
                $removed++;
            }
        }

        if ($removed === 0) {
            $this->line('No optimization artifacts found.');
        } else {
            $this->info("Removed {$removed} optimization artifact(s).");
        }

        return Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [
            ['scaffold', null, InputOption::VALUE_NONE, 'Also remove bootstrap/preload.php, php.ini.production.example, and [performance] settings'],
        ];
    }

    private function appRoot(): string
    {
        if (defined('BASE_PATH')) {
            return (string) BASE_PATH;
        }

        return (string) getcwd();
    }
}
