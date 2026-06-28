<?php

namespace Pionia\Builtins\Commands\Frontend;

use Pionia\Builtins\Commands\Concerns\ManagesFrontendSettings;
use Pionia\Console\BaseCommand;
use Pionia\Console\Command;
use Pionia\Process\Process;
use Pionia\Utils\Filesystem;

class BuildFrontendCommand extends BaseCommand
{
    use ManagesFrontendSettings;

    protected string $name = 'frontend:build';

    protected array $aliases = ['f:build', 'frontend:b'];

    protected string $description = 'Build the Vite frontend and deploy output to public/';

    protected string $help = 'Runs the configured build command and copies dist/ into public/ (SPA fallback enabled).';

    protected function handle(): int
    {
        $settings = $this->frontendSettings();
        if ($settings === []) {
            $this->error('No [frontend] section in settings.ini. Run frontend:scaffold first.');

            return Command::FAILURE;
        }

        $root = $this->frontendRoot();
        $fs = new Filesystem();

        if (!$fs->exists($root)) {
            $this->error('Frontend directory not found: ' . $root);

            return Command::FAILURE;
        }

        $buildCommand = $this->frontendBuildCommand();
        $this->info("Building in {$root} …");

        $process = Process::fromShellCommandline($buildCommand, $root);
        $process->setTimeout(900);
        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->error('Frontend build failed.');

            return Command::FAILURE;
        }

        $outputDir = $this->frontendBuildOutputDir();
        $deployDir = $this->frontendDeployDir();

        if (!is_dir($outputDir)) {
            $this->error("Build output not found: {$outputDir}");

            return Command::FAILURE;
        }

        $staticDir = $deployDir . DIRECTORY_SEPARATOR . 'static';
        if (is_dir($staticDir)) {
            $this->line('Preserving public/static/ …');
        }

        $this->mirrorDirectory($outputDir, $deployDir);

        $this->writeFrontendSettings(['SPA_FALLBACK' => 'true']);
        $this->info("Deployed build to {$deployDir}");

        return Command::SUCCESS;
    }
}
