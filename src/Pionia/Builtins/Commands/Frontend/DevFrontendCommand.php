<?php

namespace Pionia\Builtins\Commands\Frontend;

use Pionia\Builtins\Commands\Concerns\ManagesFrontendSettings;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Process\Process;

class DevFrontendCommand extends Command
{
    use ManagesFrontendSettings;

    protected string $name = 'frontend:dev';

    protected array $aliases = ['f:dev'];

    protected string $description = 'Run the Vite dev server (proxies /api to Pionia)';

    protected string $help = 'Starts Vite on port 5173 by default. Run php pionia serve or runserver in another terminal for the API.';

    protected function getOptions(): array
    {
        return [
            ['port', null, InputOption::VALUE_OPTIONAL, 'Vite dev server port', null],
        ];
    }

    protected function handle(): int
    {
        $settings = $this->frontendSettings();
        if ($settings === []) {
            $this->error('No [frontend] section in settings.ini. Run frontend:scaffold first.');

            return Command::FAILURE;
        }

        $root = $this->frontendRoot();
        if (!is_dir($root)) {
            $this->error('Frontend directory not found: ' . $root);

            return Command::FAILURE;
        }

        $devCommand = $this->frontendDevCommand();
        $port = $this->option('port') ?? $this->frontendDevPort();
        $apiPort = $this->resolveApiPort();

        $this->info("Starting Vite dev server on http://127.0.0.1:{$port}");
        $this->line("API proxy target: http://127.0.0.1:{$apiPort}/api/");
        $this->line('Start the backend separately: php pionia serve');

        $command = $devCommand . ' -- --port ' . (int) $port;
        $process = Process::fromShellCommandline($command, $root);
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());
        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
