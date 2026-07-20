<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;
use Pionia\Database\MigrationStubBuilder;

/**
 * Scaffold an add-foreign-key migration.
 */
class MakeAddForeignCommand extends Command
{
    protected string $name = 'make:migration:foreign';

    protected array $aliases = ['migrate:add-foreign'];

    protected string $description = 'Create a migration that adds a foreign key column';

    public function getArguments(): array
    {
        return [
            ['table', InputArgument::OPTIONAL, 'Table that receives the foreign key'],
            ['column', InputArgument::OPTIONAL, 'Foreign key column (e.g. user_id)'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['on', null, InputOption::VALUE_OPTIONAL, 'Referenced table'],
            ['references', null, InputOption::VALUE_OPTIONAL, 'Referenced column', 'id'],
            ['cascade', null, InputOption::VALUE_NONE, 'cascadeOnDelete()'],
            ['name', null, InputOption::VALUE_OPTIONAL, 'Custom migration basename'],
        ];
    }

    protected function handle(): int
    {
        $table = $this->argument('table') ?: $this->ask('Table name');
        $column = $this->argument('column') ?: $this->ask('Foreign column', 'user_id');

        if (!is_string($table) || $table === '' || !is_string($column) || $column === '') {
            $this->error('Table and column are required.');

            return Command::FAILURE;
        }

        $on = $this->option('on');
        $path = MigrationStubBuilder::writeAddForeign(
            $table,
            $column,
            is_string($this->option('references')) ? (string) $this->option('references') : 'id',
            is_string($on) && $on !== '' ? $on : null,
            (bool) $this->option('cascade'),
            is_string($this->option('name')) && $this->option('name') !== '' ? (string) $this->option('name') : null,
        );

        $this->info('Created migration: ' . $path);

        return Command::SUCCESS;
    }
}
