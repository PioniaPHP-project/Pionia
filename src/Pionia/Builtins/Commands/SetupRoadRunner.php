<?php

namespace Pionia\Builtins\Commands;

use Pionia\Builtins\Commands\Concerns\ManagesRoadRunnerProcess;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Http\Worker\RoadRunnerWorker;
use Pionia\Process\Process;

/**
 * Download the RoadRunner binary into the application root.
 */
class SetupRoadRunner extends Command
{
    use ManagesRoadRunnerProcess;

    protected string $name = 'rr:setup';

    protected array $aliases = ['runserver:setup'];

    protected string $description = 'Download the RoadRunner binary (./rr) for this application';

    protected string $help = 'Requires spiral/roadrunner-cli in vendor. Re-run with --force to replace an existing binary.';

    protected function handle(): int
    {
        if (!RoadRunnerWorker::isAvailable()) {
            $this->error('Missing PHP packages. Install dev dependencies:');
            $this->line('  composer require --dev spiral/roadrunner-http spiral/roadrunner-cli nyholm/psr7');

            return Command::FAILURE;
        }

        $root = $this->roadRunnerAppRoot();
        $rrGet = $this->rrGetBinary();
        if ($rrGet === null) {
            $this->error('spiral/roadrunner-cli not found. Run: composer install');

            return Command::FAILURE;
        }

        $binary = $root . DIRECTORY_SEPARATOR . 'rr';
        if (is_file($binary) && is_executable($binary) && $this->isRoadRunnerBinary($binary) && !$this->option('force')) {
            $this->info('RoadRunner binary already installed.');
            $this->line($binary);
            $this->printBinaryVersion($binary);
            $this->verifyWorkerFiles($root);

            return Command::SUCCESS;
        }

        $this->line('==> Downloading RoadRunner binary to ' . $root);
        $process = new Process([$rrGet, 'get', '-l', $root, '-n'], $root, $this->processEnv());
        $process->setTimeout(180);
        $process->run(function (string $type, string $buffer): void {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful() || !is_file($binary) || !is_executable($binary)) {
            $this->error('Failed to install ./rr');

            return Command::FAILURE;
        }

        $this->line('==> RoadRunner binary');
        $this->printBinaryVersion($binary);
        $this->verifyWorkerFiles($root);
        $this->info('Ready: php pionia runserver');

        return Command::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [
            ['force', null, InputOption::VALUE_NONE, 'Re-download even if ./rr exists'],
        ];
    }

    private function rrGetBinary(): ?string
    {
        $root = $this->roadRunnerAppRoot();
        $candidates = [
            $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'rr',
            dirname($root) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'rr',
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
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

    private function printBinaryVersion(string $binary): void
    {
        $process = new Process([$binary, '--version'], null, $this->processEnv());
        $process->setTimeout(5);
        $process->run();

        if ($process->isSuccessful()) {
            $this->line(trim($process->getOutput()));
        }
    }

    private function verifyWorkerFiles(string $root): void
    {
        $config = $root . DIRECTORY_SEPARATOR . '.rr.yaml';
        $worker = $root . DIRECTORY_SEPARATOR . 'worker.php';

        if (!is_file($config) || !is_file($worker)) {
            $this->warn('Missing worker files. Ensure .rr.yaml and worker.php exist in the app root.');

            return;
        }

        $this->line('==> Worker entry OK');
    }
}
