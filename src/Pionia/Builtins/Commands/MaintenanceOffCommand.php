<?php

namespace Pionia\Builtins\Commands;

use Pionia\Builtins\Commands\Concerns\ManagesMaintenanceSettings;
use Pionia\Console\Command;

class MaintenanceOffCommand extends Command
{
    use ManagesMaintenanceSettings;

    protected string $name = 'maintenance:off';

    protected array $aliases = ['up'];

    protected string $description = 'Disable application maintenance mode';

    protected string $help = 'Sets [maintenance] ENABLED=false in environment/settings.ini.';

    protected function handle(): int
    {
        if (!$this->maintenanceEnabledInSettings()) {
            $this->info('Application is not in maintenance mode.');

            return Command::SUCCESS;
        }

        if (!$this->writeMaintenanceSection(['ENABLED' => 'false'])) {
            $this->error('Unable to update maintenance settings in settings.ini.');

            return Command::FAILURE;
        }

        $this->info('Application is live again.');

        return Command::SUCCESS;
    }
}
