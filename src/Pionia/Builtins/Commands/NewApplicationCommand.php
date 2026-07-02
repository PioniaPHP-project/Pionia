<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\Command;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputOption;
use Pionia\Console\Input\ArgvInput;
use Pionia\Process\Process;
use Pionia\Scaffolding\ApplicationScaffolder;
use Pionia\Scaffolding\FrontendFramework;
use Pionia\Utils\Support;

class NewApplicationCommand extends Command
{
    protected string $name = 'new';

    protected array $aliases = ['pionia:new', 'create'];

    protected string $description = 'Scaffold a new Pionia application';

    protected string $help = 'Creates a new project directory with bootstrap, environment, services, and composer.json. '
        . 'Requires an existing Pionia install (php pionia from this repo or vendor). '
        . 'For a fresh machine with only Composer: composer create-project pionia/pionia-app my-api '
        . '(add -- --vue-ts to scaffold a frontend, or answer the prompt).';

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
            ['with-frontend', null, InputOption::VALUE_OPTIONAL, 'Scaffold frontend (' . FrontendFramework::listForDisplay() . '); use alone to pick from the list', null],
            ['no-frontend', null, InputOption::VALUE_NONE, 'Skip the frontend scaffold prompt'],
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

        $frontend = $this->resolveRequestedFrontend();
        if ($frontend === false) {
            return Command::FAILURE;
        }
        $shouldInstall = (bool) $this->option('install') || $frontend !== null;

        if ($shouldInstall && !$this->option('install')) {
            $this->line('Running composer install (required for frontend scaffold) …');
        }

        if ($shouldInstall) {
            if (!$this->runComposerInstall($target)) {
                return Command::FAILURE;
            }
        }

        if ($frontend !== null) {
            if (!$this->runFrontendScaffold($target, $frontend)) {
                return Command::FAILURE;
            }
        }

        $this->line('');
        $this->line('Next steps:');
        $this->line("  cd {$name}");
        if (!$shouldInstall) {
            $this->line('  composer install');
        }
        if ($frontend === null && !$this->option('no-frontend')) {
            $this->line('  php pionia frontend:scaffold   # optional Vite SPA');
        }
        $this->line('  php pionia serve   # or: composer run serve');
        if ($frontend !== null) {
            $this->line('  php pionia frontend:dev   # Vite dev server');
        }
        $this->line('');
        $this->line('Tip: on a machine without Pionia yet, prefer: composer create-project pionia/pionia-app ' . $name . ' -- --vue-ts');

        return Command::SUCCESS;
    }

    private function resolveRequestedFrontend(): string|false|null
    {
        if ($this->input instanceof ArgvInput) {
            $fromArgv = FrontendFramework::resolveFromTokens($this->input->getTokens());
            if ($fromArgv !== null) {
                return $fromArgv;
            }
        }

        $with = $this->option('with-frontend');
        if (is_string($with) && $with !== '') {
            $framework = strtolower($with);
            if (!FrontendFramework::isValid($framework)) {
                $this->error('Invalid --with-frontend value. Choose: ' . FrontendFramework::listForDisplay());

                return false;
            }

            return $framework;
        }

        if ($with === true) {
            return strtolower((string) $this->choice(
                'Choose a Vite template',
                FrontendFramework::ALL,
                FrontendFramework::DEFAULT,
            ));
        }

        if ($this->option('no-frontend') || !$this->canPromptForFrontend()) {
            return null;
        }

        if (!$this->confirm('Scaffold a Vite frontend in frontend/?', false)) {
            return null;
        }

        return strtolower((string) $this->choice(
            'Choose a Vite template',
            FrontendFramework::ALL,
            FrontendFramework::DEFAULT,
        ));
    }

    private function canPromptForFrontend(): bool
    {
        if (defined('PIONIA_TESTING') && PIONIA_TESTING) {
            return false;
        }

        if (!$this->input->isInteractive()) {
            return false;
        }

        return function_exists('posix_isatty') && @posix_isatty(STDIN);
    }

    private function runComposerInstall(string $target): bool
    {
        if ($this->option('install')) {
            $this->line('Running composer install …');
        }

        $process = Process::fromShellCommandline('composer install --no-interaction', $target);
        $process->setTimeout(600);
        $process->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->warn('composer install failed — run it manually in the project directory.');

            return false;
        }

        return true;
    }

    private function runFrontendScaffold(string $target, string $frontend): bool
    {
        $this->line("Scaffolding {$frontend} frontend …");
        $pioniaBin = $target . DIRECTORY_SEPARATOR . 'pionia';
        $scaffold = Process::fromShellCommandline(
            'php ' . escapeshellarg($pioniaBin) . ' frontend:scaffold --framework=' . escapeshellarg($frontend) . ' --yes',
            $target,
        );
        $scaffold->setTimeout(900);
        $scaffold->run(function ($type, $buffer): void {
            $this->output->write($buffer);
        });

        if (!$scaffold->isSuccessful()) {
            $this->warn("Frontend scaffold failed — run: php pionia frontend:scaffold --framework={$frontend} --yes");

            return false;
        }

        return true;
    }
}
