<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;
use Pionia\Database\MigrationStubBuilder;

/**
 * Create a migration file (interactive wizard or flags).
 *
 * Wizard: name → type (create / alter / blank / data) → table → columns → timestamps / soft deletes.
 */
class MakeMigrationCommand extends Command
{
    protected string $name = 'make:migration';

    protected array $aliases = ['migrate:make'];

    protected string $description = 'Create a new migration file';

    protected string $help = 'Interactive maker: name → type → table → columns. '
        . 'Prefer make:table / make:migration:column / make:pivot for one-shot starters.';

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
            ['columns', 'c', InputOption::VALUE_OPTIONAL, 'Column DSL: email:email:unique,name:string'],
            ['timestamps', null, InputOption::VALUE_NONE, 'Include timestamps() (with --table)'],
            ['soft-deletes', null, InputOption::VALUE_NONE, 'Include softDeletes() (with --table)'],
            ['type', null, InputOption::VALUE_OPTIONAL, 'create|alter|blank|data (non-interactive)'],
        ];
    }

    protected function handle(): int
    {
        $name = $this->argument('name');
        $table = $this->option('table');
        $type = $this->option('type');
        $columnsDsl = (string) ($this->option('columns') ?? '');
        $timestamps = (bool) $this->option('timestamps');
        $softDeletes = (bool) $this->option('soft-deletes');

        $wantsWizard = !$name && !$table && !$type && $this->input->isInteractive()
            && !(defined('PIONIA_TESTING') && PIONIA_TESTING);

        if ($wantsWizard) {
            return $this->runWizard();
        }

        // Heuristic from name when type/table omitted
        if (!$type && is_string($name) && $name !== '') {
            if (preg_match('/^create_(.+)_table$/i', $name, $m)) {
                $type = 'create';
                $table = $table ?: $m[1];
            } elseif (preg_match('/^add_.+_to_(.+)_table$/i', $name, $m)) {
                $type = 'alter';
                $table = $table ?: $m[1];
            }
        }

        if (is_string($table) && $table !== '' && ($type === null || $type === 'create')) {
            if (!$this->option('timestamps') && !$this->option('soft-deletes')) {
                $timestamps = true;
            }
            $path = MigrationStubBuilder::writeCreateTable(
                $table,
                MigrationStubBuilder::parseColumns($columnsDsl),
                $timestamps,
                $softDeletes,
                is_string($name) && $name !== '' ? $name : null,
            );
        } elseif ($type === 'alter' && is_string($table) && $table !== '') {
            $cols = MigrationStubBuilder::parseColumns($columnsDsl !== '' ? $columnsDsl : 'column:string:nullable');
            $path = MigrationStubBuilder::writeAddColumns(
                $table,
                $cols,
                is_string($name) && $name !== '' ? $name : null,
            );
        } elseif ($type === 'data') {
            $path = MigrationStubBuilder::writeBlank(is_string($name) && $name !== '' ? $name : 'data_migration');
        } else {
            $path = MigrationStubBuilder::writeBlank(is_string($name) && $name !== '' ? $name : null);
        }

        $this->info('Created migration: ' . $path);
        $this->line('Next: php pionia migrate');

        return Command::SUCCESS;
    }

    private function runWizard(): int
    {
        $name = (string) $this->ask('Migration name', 'blank_migration');
        $type = (string) $this->choice(
            'Migration type',
            ['create table', 'alter table', 'blank', 'data'],
            'create table',
        );

        if ($type === 'create table') {
            $table = (string) $this->ask('Table name');
            $columnsDsl = (string) $this->ask(
                'Columns DSL (optional, e.g. email:email:unique,name:string)',
                '',
            );
            $timestamps = $this->confirm('Add timestamps()?', true);
            $softDeletes = $this->confirm('Add softDeletes()?', false);
            $path = MigrationStubBuilder::writeCreateTable(
                $table,
                MigrationStubBuilder::parseColumns($columnsDsl),
                $timestamps,
                $softDeletes,
                $name !== '' ? $name : null,
            );
        } elseif ($type === 'alter table') {
            $table = (string) $this->ask('Table name');
            $columnsDsl = (string) $this->ask(
                'Columns to add (DSL, e.g. phone:phone:nullable)',
                'column:string:nullable',
            );
            $path = MigrationStubBuilder::writeAddColumns(
                $table,
                MigrationStubBuilder::parseColumns($columnsDsl),
                $name !== '' ? $name : null,
            );
        } else {
            $path = MigrationStubBuilder::writeBlank($name !== '' ? $name : null);
        }

        $this->info('Created migration: ' . $path);
        $this->line('Next: php pionia migrate');

        return Command::SUCCESS;
    }
}
