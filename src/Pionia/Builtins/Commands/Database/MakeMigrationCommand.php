<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;
use Pionia\Database\MigrationStubBuilder;

/**
 * Create a blank or lightly guided migration file.
 */
class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';

    protected array $aliases = ['migrate:make'];

    protected string $description = 'Create a new migration file';

    protected string $help = 'Interactive maker for blank migrations or create-table stubs. Prefer make:table / make:migration:column for structured stubs.';

    public function getArguments(): array
    {
        return [
            ['name', InputArgument::OPTIONAL, 'Migration basename (e.g. create_users_table)'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['table', 't', InputOption::VALUE_OPTIONAL, 'Create a table with this name'],
            ['columns', 'c', InputOption::VALUE_OPTIONAL, 'Column DSL: email:string:unique,name:string'],
            ['timestamps', null, InputOption::VALUE_NONE, 'Include timestamps() (with --table)'],
            ['soft-deletes', null, InputOption::VALUE_NONE, 'Include softDeletes() (with --table)'],
        ];
    }

    protected function handle(): int
    {
        $name = $this->argument('name');
        $table = $this->option('table');

        if (!$name && !$table && $this->input->isInteractive()) {
            $name = $this->ask('Migration name', 'blank_migration');
            if ($this->confirm('Create a table?', false)) {
                $table = $this->ask('Table name');
            }
        }

        if (is_string($table) && $table !== '') {
            $columns = MigrationStubBuilder::parseColumns((string) ($this->option('columns') ?? ''));
            $path = MigrationStubBuilder::writeCreateTable(
                $table,
                $columns,
                true,
                (bool) $this->option('soft-deletes'),
                is_string($name) && $name !== '' ? $name : null,
            );
        } else {
            $path = MigrationStubBuilder::writeBlank(is_string($name) && $name !== '' ? $name : null);
        }

        $this->info('Created migration: ' . $path);

        return Command::SUCCESS;
    }
}
