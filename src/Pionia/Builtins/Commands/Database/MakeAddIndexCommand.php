<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;
use Pionia\Database\MigrationStubBuilder;

/**
 * Scaffold an add-index migration.
 */
class MakeAddIndexCommand extends Command
{
    protected string $name = 'make:migration:index';

    protected array $aliases = ['migrate:add-index'];

    protected string $description = 'Create a migration that adds an index to a table';

    public function getArguments(): array
    {
        return [
            ['table', InputArgument::OPTIONAL, 'Table name'],
            ['columns', InputArgument::OPTIONAL, 'Comma-separated columns'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['unique', 'u', InputOption::VALUE_NONE, 'Create a unique index'],
            ['index-name', null, InputOption::VALUE_OPTIONAL, 'Explicit index name'],
            ['name', null, InputOption::VALUE_OPTIONAL, 'Custom migration basename'],
        ];
    }

    protected function handle(): int
    {
        $table = $this->argument('table') ?: $this->ask('Table name');
        $columnsRaw = $this->argument('columns') ?: $this->ask('Columns (comma-separated)');

        if (!is_string($table) || $table === '' || !is_string($columnsRaw) || $columnsRaw === '') {
            $this->error('Table and columns are required.');

            return Command::FAILURE;
        }

        $columns = array_values(array_filter(array_map('trim', explode(',', $columnsRaw))));
        $path = MigrationStubBuilder::writeAddIndex(
            $table,
            $columns,
            $this->option('unique') ? 'unique' : 'index',
            is_string($this->option('index-name')) ? (string) $this->option('index-name') : null,
            is_string($this->option('name')) && $this->option('name') !== '' ? (string) $this->option('name') : null,
        );

        $this->info('Created migration: ' . $path);

        return Command::SUCCESS;
    }
}
