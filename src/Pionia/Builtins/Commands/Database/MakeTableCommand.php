<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;
use Pionia\Database\MigrationStubBuilder;

/**
 * Scaffold a create-table migration.
 */
class MakeTableCommand extends Command
{
    protected string $name = 'make:table';

    protected array $aliases = ['migrate:make-table'];

    protected string $description = 'Create a migration that builds a new table';

    protected string $help = 'Generates Schema::create() with id(), optional column DSL, timestamps, and soft deletes.';

    public function getArguments(): array
    {
        return [
            ['table', InputArgument::OPTIONAL, 'Table name'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['columns', 'c', InputOption::VALUE_OPTIONAL, 'Column DSL: email:string:unique,name:string'],
            ['timestamps', null, InputOption::VALUE_NONE, 'Include timestamps()'],
            ['no-timestamps', null, InputOption::VALUE_NONE, 'Omit timestamps()'],
            ['soft-deletes', null, InputOption::VALUE_NONE, 'Include softDeletes()'],
            ['name', null, InputOption::VALUE_OPTIONAL, 'Custom migration basename'],
        ];
    }

    protected function handle(): int
    {
        $table = $this->argument('table');
        if (!is_string($table) || $table === '') {
            $table = $this->ask('Table name');
        }
        if (!is_string($table) || $table === '') {
            $this->error('Table name is required.');

            return Command::FAILURE;
        }

        $columnsDsl = $this->option('columns');
        if ((!is_string($columnsDsl) || $columnsDsl === '') && $this->input->isInteractive()) {
            $columnsDsl = $this->ask('Columns DSL (optional)', '');
        }

        $columns = MigrationStubBuilder::parseColumns(is_string($columnsDsl) ? $columnsDsl : '');
        $timestamps = !$this->option('no-timestamps');
        if ($this->option('timestamps')) {
            $timestamps = true;
        }

        $path = MigrationStubBuilder::writeCreateTable(
            $table,
            $columns,
            $timestamps,
            (bool) $this->option('soft-deletes'),
            is_string($this->option('name')) && $this->option('name') !== '' ? (string) $this->option('name') : null,
        );

        $this->info('Created migration: ' . $path);

        return Command::SUCCESS;
    }
}
