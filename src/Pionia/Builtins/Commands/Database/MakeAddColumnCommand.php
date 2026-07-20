<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;
use Pionia\Database\MigrationStubBuilder;

/**
 * Scaffold an add-column(s) migration.
 */
class MakeAddColumnCommand extends Command
{
    protected string $name = 'make:migration:column';

    protected array $aliases = ['migrate:add-column'];

    protected string $description = 'Create a migration that adds columns to a table';

    public function getArguments(): array
    {
        return [
            ['table', InputArgument::OPTIONAL, 'Table name'],
            ['columns', InputArgument::OPTIONAL, 'Column DSL: email:string:unique,bio:text:nullable'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['name', null, InputOption::VALUE_OPTIONAL, 'Custom migration basename'],
        ];
    }

    protected function handle(): int
    {
        $table = $this->argument('table') ?: $this->ask('Table name');
        $columnsDsl = $this->argument('columns') ?: $this->ask('Columns DSL', '');

        if (!is_string($table) || $table === '') {
            $this->error('Table name is required.');

            return Command::FAILURE;
        }

        $columns = MigrationStubBuilder::parseColumns(is_string($columnsDsl) ? $columnsDsl : '');
        if ($columns === []) {
            $this->error('At least one column is required.');

            return Command::FAILURE;
        }

        $path = MigrationStubBuilder::writeAddColumns(
            $table,
            $columns,
            is_string($this->option('name')) && $this->option('name') !== '' ? (string) $this->option('name') : null,
        );

        $this->info('Created migration: ' . $path);

        return Command::SUCCESS;
    }
}
