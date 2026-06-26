<?php

namespace Pionia\Builtins\Commands;

use Pionia\Builtins\Commands\Concerns\ManagesRoadRunnerProcess;
use Pionia\Console\BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;

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
        $port = $this->resolveHttpPort($config);

        if (!is_file($pidFile) && !$this->isPortListening($port)) {
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

        $this->stopListenersOnPort($port, (bool) $this->option('force'));

        if (is_file($pidFile)) {
            @unlink($pidFile);
        }

        if ($this->isPortListening($port)) {
            $this->error("Port {$port} is still in use. Try: php pionia stopserver --force");

            return Command::FAILURE;
        }

        $this->output->writeln('<info>RoadRunner stopped.</info>');

        return Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [
            ['config', null, InputOption::VALUE_OPTIONAL, 'Path to .rr.yaml', null],
            ['port', null, InputOption::VALUE_OPTIONAL, 'HTTP port to stop (defaults to .rr.yaml http.address)', null],
            ['force', 'f', InputOption::VALUE_NONE, 'Force stop listeners on the HTTP port'],
        ];
    }

    private function resolveHttpPort(string $configPath): int
    {
        $portOverride = $this->option('port');
        if (is_scalar($portOverride) && $portOverride !== '' && $portOverride !== false) {
            return (int) $portOverride;
        }

        return (int) $this->resolveListenAddress($configPath)['port'];
    }

    private function appRoot(): string
    {
        if (defined('BASE_PATH')) {
            return (string) BASE_PATH;
        }

        return (string) getcwd();
    }

    private function resolveConfigPath(): string
    {
        $custom = $this->option('config');
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return $this->appRoot() . DIRECTORY_SEPARATOR . '.rr.yaml';
    }

    private function resolveRoadRunnerBinary(): ?string
    {
        $candidate = $this->appRoot() . DIRECTORY_SEPARATOR . 'rr';

        return is_file($candidate) && is_executable($candidate) ? $candidate : null;
    }
}
