<?php

namespace Pionia\Builtins\Commands\Database;

use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Database\Migrations\Migrator;

/**
 * Roll back the last migration batch (or more with --step).
 */
class MigrateRollbackCommand extends Command
{
    protected string $name = 'migrate:rollback';

    protected array $aliases = ['migrate:down'];

    protected string $description = 'Roll back the last database migration batch';

    protected function getOptions(): array
    {
        return [
            ['step', 's', InputOption::VALUE_OPTIONAL, 'Number of batches to roll back', '1'],
            ['database', 'd', InputOption::VALUE_OPTIONAL, 'Connection name', 'default'],
        ];
    }

    protected function handle(): int
    {
        $migrator = new Migrator(is_string($this->option('database')) ? (string) $this->option('database') : 'default');
        $migrator->discoverDefaultPaths();

        $steps = max(1, (int) $this->option('step'));
        $rolled = $migrator->rollback($steps);

        if ($rolled === []) {
            $this->info('Nothing to roll back.');

            return Command::SUCCESS;
        }

        foreach ($rolled as $name) {
            $this->line('Rolled back: <comment>' . $name . '</comment>');
        }

        return Command::SUCCESS;
    }
}
