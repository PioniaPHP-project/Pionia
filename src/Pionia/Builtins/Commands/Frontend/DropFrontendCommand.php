<?php

namespace Pionia\Builtins\Commands\Frontend;

use Pionia\Builtins\Commands\Concerns\ManagesFrontendSettings;
use Pionia\Console\BaseCommand;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Utils\Filesystem;

class DropFrontendCommand extends BaseCommand
{
    use ManagesFrontendSettings;

    protected string $name = 'frontend:drop';

    protected array $aliases = ['f:drop'];

    protected string $description = 'Remove the frontend source directory and [frontend] settings';

    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Skip confirmation'],
        ];
    }

    protected function handle(): int
    {
        $root = $this->frontendRoot();

        if (!$this->option('force') && !$this->confirm('Delete frontend directory and settings?', false)) {
            $this->info('Aborted.');

            return Command::SUCCESS;
        }

        $fs = new Filesystem();
        if ($fs->exists($root)) {
            $fs->remove($root);
            $this->info('Removed ' . $root);
        }

        $path = app()->envPath('settings.ini');
        if (is_file($path)) {
            $config = parse_ini_file($path, true) ?: [];
            unset($config['frontend']);
            writeIniFile($path, $config);
            clearstatcache(true, $path);
        }

        $this->info('Frontend configuration removed.');

        return Command::SUCCESS;
    }
}
