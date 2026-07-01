<?php

namespace Pionia\Builtins\Commands;

use Pionia\Builtins\Commands\Concerns\FormatsRoadRunnerLogOutput;
use Pionia\Builtins\Commands\Concerns\ManagesRoadRunnerProcess;
use Pionia\Http\Worker\RoadRunnerWorker;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Utils\Filesystem;
use Pionia\Process\Process;

/**
 * Serve the application via RoadRunner (persistent PHP workers).
 */
class StartRoadRunnerServer extends Command
{
    use FormatsRoadRunnerLogOutput;
    use ManagesRoadRunnerProcess;
    protected string $name = 'runserver';

    protected array $aliases = ['roadrunner', 'rr:serve'];

    protected string $description = 'Serve the application with RoadRunner (persistent workers)';

    protected string $help = 'Requires the RoadRunner binary (rr) and spiral/roadrunner-http. Use for production-like local testing.';

    protected function handle(): int
    {
        if (!RoadRunnerWorker::isAvailable()) {
            $this->error('Missing PHP packages. Install: composer require spiral/roadrunner-http nyholm/psr7');

            return Command::FAILURE;
        }

        $config = $this->resolveConfigPath();
        if (!is_file($config)) {
            $this->error("RoadRunner config not found: {$config}");
            $this->line('Copy example/.rr.yaml to your app root or pass --config=');

            return Command::FAILURE;
        }

        $worker = $this->resolveWorkerPath();
        if (!is_file($worker)) {
            $this->error("Worker entry not found: {$worker}");

            return Command::FAILURE;
        }

        $binary = $this->resolveRoadRunnerBinary();
        if ($binary === null) {
            $this->error('RoadRunner binary (rr) not found.');
            $this->line('Install into your app root:');
            foreach ($this->rrInstallCommands() as $line) {
                $this->line('  ' . $line);
            }
            $this->line('Or download from https://roadrunner.dev/docs/intro-install');
            $this->warn('Note: a shell alias `rr` (e.g. Rails) is ignored — use an explicit ./rr binary.');

            return Command::FAILURE;
        }

        $listen = $this->resolveListenAddress(
            $config,
            is_string($this->option('host')) ? $this->option('host') : null,
            $this->option('port'),
        );
        $host = $listen['host'];
        $port = $listen['port'];
        $httpAddress = $listen['address'];
        $cwd = dirname($config);

        if ($this->isPortListening((int) $port)) {
            $this->error("Port {$port} is already in use.");
            $this->line('Stop the running instance with: php pionia stopserver');

            return Command::FAILURE;
        }

        $command = $this->buildServeCommand(
            $binary,
            $cwd,
            $config,
            $httpAddress,
            $listen['yaml_address'],
            true,
            (bool) $this->option('detach'),
        );

        if ($this->option('detach')) {
            return $this->startDetached($command, $cwd, $httpAddress);
        }

        $this->output->writeln('<info>Starting RoadRunner</info> for ' . realm()->getAppName());
        $this->output->writeln("Config: {$config}");
        $this->output->writeln("Worker: {$worker}");
        $this->output->writeln("Binary: {$binary}");
        $this->output->writeln("HTTP: http://{$httpAddress}");
        $this->output->writeln('Press Ctrl+C to stop');

        $process = new Process(
            $command,
            $cwd,
            $this->processEnv(['PIONIA_RUNTIME' => 'worker']),
            null,
            null,
        );

        $process->setTimeout(null);

        if (Process::isTtySupported()) {
            $process->setTty(true);
        }

        $process->run(function (string $type, string $buffer): void {
            $this->writeRoadRunnerLogChunk($buffer);
        });

        $this->flushRoadRunnerLogWriter();

        return $process->getExitCode() ?? Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [
            ['config', null, InputOption::VALUE_OPTIONAL, 'Path to .rr.yaml', null],
            ['worker', null, InputOption::VALUE_OPTIONAL, 'Path to worker.php', null],
            ['host', null, InputOption::VALUE_OPTIONAL, 'HTTP host (overrides env/settings/.rr.yaml)', null],
            ['port', null, InputOption::VALUE_OPTIONAL, 'HTTP port (overrides env/settings/.rr.yaml; default 9000)', null],
            ['detach', 'D', InputOption::VALUE_NONE, 'Run RoadRunner in the background (writes .pid, logs to storage/logs/roadrunner.log)'],
            ['log', null, InputOption::VALUE_OPTIONAL, 'Log file when using --detach', null],
            ['raw', null, InputOption::VALUE_NONE, 'Print RoadRunner output without formatting'],
        ];
    }

    /**
     * @return list<string>
     */
    private function buildServeCommand(
        string $binary,
        string $cwd,
        string $config,
        string $httpAddress,
        string $configuredAddress,
        bool $pidFile,
        bool $silent = false,
    ): array {
        $command = [$binary, '-w', $cwd];

        if ($pidFile) {
            $command[] = '-p';
        }

        if ($silent) {
            $command[] = '-s';
        }

        $command[] = 'serve';
        $command[] = '-c';
        $command[] = $config;

        if ($httpAddress !== $configuredAddress) {
            $command[] = '-o';
            $command[] = 'http.address=' . $httpAddress;
        }

        return $command;
    }

    private function startDetached(array $command, string $cwd, string $httpAddress): int
    {
        $pidFile = $cwd . DIRECTORY_SEPARATOR . '.pid';
        $port = (int) substr(strrchr($httpAddress, ':') ?: ':0', 1);

        if (is_file($pidFile)) {
            if ($this->isPortListening($port)) {
                $this->error('RoadRunner already appears to be running (' . $pidFile . ' exists).');
                $this->line('Stop it with: php pionia stopserver');

                return Command::FAILURE;
            }

            @unlink($pidFile);
        }

        $log = $this->resolveRoadRunnerLogPath($this->option('log'));
        $dir = dirname($log);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->error("Could not create log directory: {$dir}");

            return Command::FAILURE;
        }

        $escaped = implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $command));
        $shell = 'nohup ' . $escaped . ' >> ' . escapeshellarg($log) . ' 2>&1 &';

        $process = Process::fromShellCommandline($shell, $cwd, $this->processEnv(['PIONIA_RUNTIME' => 'worker']));
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Failed to start RoadRunner in the background.');
            if ($process->getErrorOutput() !== '') {
                $this->line($process->getErrorOutput());
            }

            return Command::FAILURE;
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            if (is_file($pidFile)) {
                break;
            }

            usleep(100_000);
        }

        $pid = is_readable($pidFile) ? trim((string) file_get_contents($pidFile)) : null;

        [$host, $port] = array_pad(explode(':', $httpAddress, 2), 2, null);
        $this->writeRoadRunnerRuntime($cwd, [
            'http_address' => $httpAddress,
            'host' => $host,
            'port' => (int) $port,
            'config' => $this->resolveConfigPath(),
            'pid' => $pid,
            'started_at' => date('c'),
        ]);

        $this->output->writeln('<info>RoadRunner started in background</info> for ' . realm()->getAppName());
        $this->output->writeln("HTTP: http://{$httpAddress}");
        if ($pid !== null && $pid !== '') {
            $this->output->writeln("PID: {$pid}");
        }
        $this->output->writeln("PID file: {$pidFile}");
        $this->output->writeln("Log: {$log}");
        $this->output->writeln('Stop with: php pionia stopserver');

        return Command::SUCCESS;
    }

    private function resolveConfigPath(): string
    {
        $custom = $this->option('config');
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return $this->roadRunnerAppRoot() . DIRECTORY_SEPARATOR . '.rr.yaml';
    }

    private function resolveWorkerPath(): string
    {
        $custom = $this->option('worker');
        if (is_string($custom) && $custom !== '') {
            return $custom;
        }

        return $this->roadRunnerAppRoot() . DIRECTORY_SEPARATOR . 'worker.php';
    }

    private function resolveRoadRunnerBinary(): ?string
    {
        $root = $this->roadRunnerAppRoot();
        $candidates = [
            $root . DIRECTORY_SEPARATOR . 'rr',
        ];

        $fs = new Filesystem();
        foreach ($candidates as $candidate) {
            if (!$fs->exists($candidate) || !is_executable($candidate)) {
                continue;
            }

            if ($this->isRoadRunnerBinary($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isRoadRunnerBinary(string $path): bool
    {
        $process = new Process([$path, '--version'], null, $this->processEnv());
        $process->setTimeout(5);
        $process->run();

        if (!$process->isSuccessful()) {
            return false;
        }

        $output = strtolower($process->getOutput());

        return str_contains($output, 'roadrunner') || str_starts_with(trim($output), 'rr version');
    }

    /**
     * @return list<string>
     */
    private function rrInstallCommands(): array
    {
        $root = $this->roadRunnerAppRoot();
        $lines = [];

        $rrGet = $this->rrGetBinary();
        if ($rrGet !== null) {
            $lines[] = $rrGet . ' get -l ' . $root;
        }

        $repoVendor = dirname($root) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'rr';
        if ($rrGet === null && is_file($repoVendor)) {
            $lines[] = $repoVendor . ' get -l ' . $root;
        }

        if ($lines === []) {
            $lines[] = 'composer require --dev spiral/roadrunner-cli';
            $lines[] = 'vendor/bin/rr get -l ' . $root;
        }

        return $lines;
    }

    private function rrGetBinary(): ?string
    {
        $candidates = [
            $this->roadRunnerAppRoot() . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'rr',
            dirname($this->roadRunnerAppRoot()) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'rr',
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
