<?php

namespace Pionia\Builtins\Commands\Frontend;

use Pionia\Builtins\Commands\Concerns\ManagesFrontendSettings;
use Pionia\Console\BaseCommand;
use Pionia\Console\Command;
use Pionia\Http\PublicEntryPoint;
use Pionia\Utils\Filesystem;

class CleanFrontendCommand extends BaseCommand
{
    use ManagesFrontendSettings;

    protected string $name = 'frontend:clean';

    protected array $aliases = ['f:clean'];

    protected string $description = 'Remove built frontend assets from public/ (keeps public/static/)';

    protected function handle(): int
    {
        $deployDir = $this->frontendDeployDir();
        $fs = new Filesystem();

        if (!$fs->exists($deployDir)) {
            $this->info('Nothing to clean.');

            return Command::SUCCESS;
        }

        $preserve = PublicEntryPoint::PRESERVE_IN_PUBLIC;
        $removed = 0;

        foreach (scandir($deployDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $preserve, true)) {
                continue;
            }

            $path = $deployDir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $fs->remove($path);
            } else {
                unlink($path);
            }
            $removed++;
        }

        $this->info("Removed {$removed} deployed frontend item(s) from public/.");

        return Command::SUCCESS;
    }
}
