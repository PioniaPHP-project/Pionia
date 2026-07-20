<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Database\Migrations\Migrator;

/**
 * Show which migrations have run and which are pending.
 */
class MigrateStatusCommand extends Command
{
    protected string $name = 'migrate:status';

    protected array $aliases = ['migrate:list'];

    protected string $description = 'Show the status of each migration';

    protected function getOptions(): array
    {
        return [
            ['database', 'd', InputOption::VALUE_OPTIONAL, 'Connection name', 'default'],
        ];
    }

    protected function handle(): int
    {
        $migrator = new Migrator(is_string($this->option('database')) ? (string) $this->option('database') : 'default');
        $migrator->discoverDefaultPaths();

        $rows = $migrator->status();
        if ($rows === []) {
            $this->info('No migrations found.');

            return Command::SUCCESS;
        }

        $this->table(
            ['Migration', 'Batch', 'Status', 'Source'],
            array_map(static fn (array $row) => [
                $row['name'],
                $row['batch'] ?? '-',
                $row['status'],
                $row['source'],
            ], $rows),
        );

        return Command::SUCCESS;
    }
}
