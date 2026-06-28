<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\BaseCommand;
use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;
use Pionia\Process\Process;
use Pionia\Scaffolding\ApplicationScaffolder;
use Pionia\Utils\Support;

class NewApplicationCommand extends BaseCommand
{
    protected string $name = 'new';

    protected array $aliases = ['pionia:new', 'create'];

    protected string $description = 'Scaffold a new Pionia application';

    protected string $help = 'Creates a new project directory with bootstrap, environment, services, and composer.json.';

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'Application directory name'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['path', null, InputOption::VALUE_OPTIONAL, 'Parent directory for the new app', getcwd() ?: '.'],
            ['vendor', null, InputOption::VALUE_OPTIONAL, 'Composer vendor name', 'app'],
            ['with-frontend', null, InputOption::VALUE_OPTIONAL, 'Scaffold frontend (react-ts, vue-ts, …)', null],
            ['install', null, InputOption::VALUE_NONE, 'Run composer install after scaffold'],
        ];
    }

    protected function handle(): int
    {
        $name = (string) $this->argument('name');
        $parent = (string) ($this->option('path') ?: getcwd() ?: '.');
        $vendor = (string) ($this->option('vendor') ?: 'app');

        if (preg_match('/[^a-zA-Z0-9_-]/', $name)) {
            $this->error('Application name may only contain letters, numbers, hyphens, and underscores.');

            return Command::FAILURE;
        }

        $target = rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $name;
        $appName = Support::classify(str_replace(['-', '_'], ' ', $name));
        $slug = Support::slugify($name);

        $replacements = [
            '{{APP_NAME}}' => $appName,
            '{{APP_SLUG}}' => $slug,
            '{{VENDOR}}' => $vendor,
        ];

        try {
            (new ApplicationScaffolder())->scaffold($target, $replacements);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info("Application scaffolded at {$target}");

        if ($this->option('install')) {
            $this->line('Running composer install …');
            $process = Process::fromShellCommandline('composer install --no-interaction', $target);
            $process->setTimeout(600);
            $process->run(function ($type, $buffer): void {
                $this->output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $this->warn('composer install failed — run it manually in the project directory.');

                return Command::FAILURE;
            }
        }

        $frontend = $this->option('with-frontend');
        if (is_string($frontend) && $frontend !== '' && $this->option('install')) {
            $this->line('Scaffolding frontend …');
            $scaffold = Process::fromShellCommandline(
                'php pionia frontend:scaffold --framework=' . escapeshellarg($frontend) . ' --yes',
                $target,
            );
            $scaffold->setTimeout(900);
            $scaffold->run(function ($type, $buffer): void {
                $this->output->write($buffer);
            });
        }

        $this->line('');
        $this->line('Next steps:');
        $this->line("  cd {$name}");
        if (!$this->option('install')) {
            $this->line('  composer install');
        }
        if (is_string($frontend) && $frontend !== '' && !$this->option('install')) {
            $this->line("  php pionia frontend:scaffold --framework={$frontend} --yes");
        }
        $this->line('  php pionia serve');

        return Command::SUCCESS;
    }
}
