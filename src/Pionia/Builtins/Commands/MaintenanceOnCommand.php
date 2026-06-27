<?php

namespace Pionia\Builtins\Commands;

use Pionia\Builtins\Commands\Concerns\ManagesMaintenanceSettings;
use Pionia\Console\BaseCommand;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;

class MaintenanceOnCommand extends BaseCommand
{
    use ManagesMaintenanceSettings;

    protected string $name = 'maintenance:on';

    protected array $aliases = ['down'];

    protected string $description = 'Put the application into maintenance mode (HTTP 503)';

    protected string $help = 'Updates [maintenance] in environment/settings.ini. RoadRunner workers pick this up on the next request without a restart.';

    protected function getOptions(): array
    {
        return [
            ['message', 'm', InputOption::VALUE_OPTIONAL, 'Maintenance message shown to visitors'],
            ['retry-after', null, InputOption::VALUE_OPTIONAL, 'Retry-After header value in seconds'],
            ['bypass', null, InputOption::VALUE_OPTIONAL, 'Secret bypass token (?bypass= or X-Maintenance-Bypass header)'],
            ['secret', 's', InputOption::VALUE_OPTIONAL, 'Alias for --bypass'],
        ];
    }

    protected function handle(): int
    {
        $maintenance = ['ENABLED' => 'true'];

        $message = $this->option('message');
        if (is_string($message) && $message !== '') {
            $maintenance['MESSAGE'] = $message;
        }

        $retryAfter = $this->option('retry-after');
        if ($retryAfter !== null && $retryAfter !== '') {
            $maintenance['RETRY_AFTER'] = (string) max(0, (int) $retryAfter);
        }

        $bypass = $this->option('bypass') ?? $this->option('secret');
        if (is_string($bypass) && $bypass !== '') {
            $maintenance['BYPASS_TOKEN'] = $bypass;
        }

        if (!$this->writeMaintenanceSection($maintenance)) {
            $this->error('Unable to update maintenance settings in settings.ini.');

            return Command::FAILURE;
        }

        $this->info('Application is now in maintenance mode.');

        if (isset($maintenance['MESSAGE'])) {
            $this->line('Message: ' . $maintenance['MESSAGE']);
        }

        if (isset($maintenance['BYPASS_TOKEN'])) {
            $this->line('Bypass: append ?bypass=' . $maintenance['BYPASS_TOKEN'] . ' or send X-Maintenance-Bypass header.');
        }

        $this->line('Bring the app back with: <comment>maintenance:off</comment> (alias: <comment>up</comment>)');

        return Command::SUCCESS;
    }
}
