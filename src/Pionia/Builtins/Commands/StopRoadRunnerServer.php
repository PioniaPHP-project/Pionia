<?php

namespace Pionia\Builtins\Commands;

use Pionia\Builtins\Commands\Concerns\ManagesRoadRunnerProcess;
use Pionia\Console\BaseCommand;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Process\Process;
use Pionia\Process\ProcessSignaledException;

/**
 * Stop RoadRunner instances for this app (foreground, detached, or orphaned).
 */
class StopRoadRunnerServer extends BaseCommand
{
    use ManagesRoadRunnerProcess;

    protected string $name = 'stopserver';

    protected array $aliases = ['roadrunner:stop', 'rr:stop'];

    protected string $description = 'Stop RoadRunner servers for this application';

    protected function handle(): int
    {
        $config = $this->resolveConfigPath();
        if (!is_file($config)) {
            $this->error("RoadRunner config not found: {$config}");

            return Command::FAILURE;
        }

        $binary = $this->resolveRoadRunnerBinary();
        if ($binary === null) {
            $this->error('RoadRunner binary (rr) not found.');

            return Command::FAILURE;
        }

        $cwd = dirname($config);
        $pidFile = $cwd . DIRECTORY_SEPARATOR . '.pid';
        $ports = $this->resolveRoadRunnerPorts($config, $this->option('port'));
        $anyListener = false;
        foreach ($ports as $port) {
            if ($this->isPortListening($port)) {
                $anyListener = true;
                break;
            }
        }

        if (!is_file($pidFile) && !$anyListener) {
            $this->warn('No running RoadRunner instance found.');

            return Command::SUCCESS;
        }

        if (is_file($pidFile)) {
            $stop = [$binary, 'stop', '-w', $cwd, '-c', $config, '-p'];
            if ($this->option('force')) {
                $stop[] = '-f';
            }

            $process = new Process($stop, $cwd, $this->processEnv());

            try {
                $process->run(function (string $type, string $buffer): void {
                    $this->output->write($buffer);
                });
            } catch (ProcessSignaledException) {
            }
        }

        $force = (bool) $this->option('force');
        foreach ($ports as $port) {
            $this->stopListenersOnPort($port, $force);
        }

        if (is_file($pidFile)) {
            @unlink($pidFile);
        }

        $stillListening = [];
        foreach ($ports as $port) {
            if ($this->isPortListening($port)) {
                $stillListening[] = $port;
            }
        }

        if ($stillListening !== []) {
            $portList = implode(', ', array_map('strval', $stillListening));
            $this->error("Port(s) still in use: {$portList}. Try: php pionia stopserver --force");

            return Command::FAILURE;
        }

        $this->clearRoadRunnerRuntime($cwd);
        $this->output->writeln('<info>RoadRunner stopped.</info>');

        return Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [
            ['config', null, InputOption::VALUE_OPTIONAL, 'Path to .rr.yaml', null],
            ['port', null, InputOption::VALUE_OPTIONAL, 'HTTP port to stop (defaults to resolved listen port)', null],
            ['force', 'f', InputOption::VALUE_NONE, 'Force stop listeners on the HTTP port'],
        ];
    }

    private function resolveConfigPath(): string
    {
        $custom = $this->option('config');
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return $this->roadRunnerAppRoot() . DIRECTORY_SEPARATOR . '.rr.yaml';
    }

    private function resolveRoadRunnerBinary(): ?string
    {
        $candidate = $this->roadRunnerAppRoot() . DIRECTORY_SEPARATOR . 'rr';

        return is_file($candidate) && is_executable($candidate) ? $candidate : null;
    }
}
