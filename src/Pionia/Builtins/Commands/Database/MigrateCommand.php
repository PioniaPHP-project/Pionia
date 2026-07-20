<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Database\Migrations\Migrator;

/**
 * Run all pending database migrations.
 */
class MigrateCommand extends Command
{
    protected string $name = 'migrate';

    protected array $aliases = ['migrate:up'];

    protected string $description = 'Run pending database migrations';

    protected function getOptions(): array
    {
        return [
            ['database', 'd', InputOption::VALUE_OPTIONAL, 'Connection name', 'default'],
            ['path', null, InputOption::VALUE_OPTIONAL, 'Only run migrations from this path'],
        ];
    }

    protected function handle(): int
    {
        $migrator = new Migrator(is_string($this->option('database')) ? (string) $this->option('database') : 'default');

        if (is_string($this->option('path')) && $this->option('path') !== '') {
            $migrator->path((string) $this->option('path'), 'app');
        } else {
            $migrator->discoverDefaultPaths();
        }

        $ran = $migrator->migrate();
        if ($ran === []) {
            $this->info('Nothing to migrate.');

            return Command::SUCCESS;
        }

        foreach ($ran as $name) {
            $this->line('Migrated: <info>' . $name . '</info>');
        }

        $this->info('Migrated ' . count($ran) . ' migration(s).');

        return Command::SUCCESS;
    }
}
