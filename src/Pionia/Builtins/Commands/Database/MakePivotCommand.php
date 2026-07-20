<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;
use Pionia\Database\MigrationStubBuilder;

/**
 * Scaffold a many-to-many pivot migration.
 */
class MakePivotCommand extends Command
{
    protected string $name = 'make:pivot';

    protected array $aliases = ['make:migration:pivot', 'migrate:make-pivot'];

    protected string $description = 'Create a many-to-many pivot table migration';

    public function getArguments(): array
    {
        return [
            ['left', InputArgument::OPTIONAL, 'First related table (e.g. posts)'],
            ['right', InputArgument::OPTIONAL, 'Second related table (e.g. tags)'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['table', null, InputOption::VALUE_OPTIONAL, 'Custom pivot table name'],
            ['timestamps', null, InputOption::VALUE_NONE, 'Add created_at / updated_at on the pivot'],
            ['name', null, InputOption::VALUE_OPTIONAL, 'Custom migration basename'],
        ];
    }

    protected function handle(): int
    {
        $left = $this->argument('left') ?: $this->ask('Left table (e.g. posts)');
        $right = $this->argument('right') ?: $this->ask('Right table (e.g. tags)');

        if (!is_string($left) || $left === '' || !is_string($right) || $right === '') {
            $this->error('Both related tables are required.');

            return Command::FAILURE;
        }

        $pivot = $this->option('table');
        $path = MigrationStubBuilder::writeManyToMany(
            $left,
            $right,
            (bool) $this->option('timestamps'),
            is_string($pivot) && $pivot !== '' ? $pivot : null,
            is_string($this->option('name')) && $this->option('name') !== '' ? (string) $this->option('name') : null,
        );

        $this->info('Created migration: ' . $path);
        $this->line('Next: php pionia migrate');

        return Command::SUCCESS;
    }
}
