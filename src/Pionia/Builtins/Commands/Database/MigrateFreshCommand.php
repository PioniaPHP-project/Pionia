<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Database\Migrations\MigrationException;
use Pionia\Database\Migrations\Migrator;

/**
 * Drop all tables and re-run every migration from scratch.
 */
class MigrateFreshCommand extends Command
{
    protected string $name = 'migrate:fresh';

    protected array $aliases = [];

    protected string $description = 'Drop all tables and re-run all migrations';

    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Required on non-sqlite databases'],
            ['database', 'd', InputOption::VALUE_OPTIONAL, 'Connection name', 'default'],
        ];
    }

    protected function handle(): int
    {
        $migrator = new Migrator(is_string($this->option('database')) ? (string) $this->option('database') : 'default');
        $migrator->discoverDefaultPaths();

        try {
            $ran = $migrator->fresh((bool) $this->option('force'));
        } catch (MigrationException $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Database refreshed. Migrated ' . count($ran) . ' migration(s).');
        foreach ($ran as $name) {
            $this->line('Migrated: <info>' . $name . '</info>');
        }

        return Command::SUCCESS;
    }
}
