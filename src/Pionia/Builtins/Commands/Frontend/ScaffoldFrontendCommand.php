<?php

namespace Pionia\Builtins\Commands\Frontend;

use Pionia\Builtins\Commands\Concerns\ManagesFrontendSettings;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;
use Pionia\Scaffolding\FrontendFramework;
use Pionia\Scaffolding\FrontendScaffolder;

class ScaffoldFrontendCommand extends Command
{
    use ManagesFrontendSettings;

    protected string $name = 'frontend:scaffold';

    protected array $aliases = ['f:scaffold', 'frontend:s'];

    protected string $description = 'Scaffold a Vite frontend (React, Vue, etc.) in frontend/';

    protected string $help = 'Creates a Vite project, writes [frontend] settings, and configures VITE_API_URL for the Pionia API.';

    /** @var list<string> */
    private array $packageManagers = ['npm', 'pnpm', 'yarn', 'bun'];

    protected function getOptions(): array
    {
        return [
            ['framework', 'f', InputOption::VALUE_OPTIONAL, 'Vite template (react, vue, react-ts, vue-ts, …)', FrontendFramework::DEFAULT],
            ['directory', 'd', InputOption::VALUE_OPTIONAL, 'Frontend root directory name', 'frontend'],
            ['package-manager', 'p', InputOption::VALUE_OPTIONAL, 'Package manager (npm, pnpm, yarn, bun)', 'npm'],
            ['yes', 'y', InputOption::VALUE_NONE, 'Skip prompts and use defaults'],
        ];
    }

    protected function handle(): int
    {
        $framework = strtolower((string) $this->option('framework'));
        $directory = (string) ($this->option('directory') ?: 'frontend');
        $packageManager = (string) ($this->option('package-manager') ?: 'npm');
        $yes = (bool) $this->option('yes');

        if (!FrontendFramework::isValid($framework)) {
            if ($yes) {
                $this->error('Invalid framework. Choose: ' . FrontendFramework::listForDisplay());

                return Command::FAILURE;
            }

            $framework = strtolower((string) $this->choice('Choose a Vite template', FrontendFramework::ALL, FrontendFramework::DEFAULT));
        }

        if (!$yes && !in_array($packageManager, $this->packageManagers, true)) {
            $packageManager = (string) $this->choice('Package manager', $this->packageManagers, 'npm');
        }

        try {
            (new FrontendScaffolder(app()->appRoot(), fn (string $line) => $this->info($line)))
                ->scaffold($framework, $directory, $packageManager);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return Command::FAILURE;
        }

        $this->info('Frontend scaffold complete.');
        $this->line('Dev:  <comment>php pionia frontend:dev</comment>');
        $this->line('Prod: <comment>php pionia frontend:build</comment> then serve from public/');

        return Command::SUCCESS;
    }
}
