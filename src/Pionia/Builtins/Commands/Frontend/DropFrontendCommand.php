<?php

namespace Pionia\Command\Commands\Frontend;

use Pionia\Command\BaseCommand;
use Pionia\Core\Helpers\Utilities;
use Pionia\Core\Pionia;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Drops an entire frontend
 *
 * @since 1.1.6
 *
 * */
class DropFrontendCommand extends BaseCommand
{
    protected string $directory = 'frontend';
    protected string $name = 'frontend:drop';
    protected string $description = 'Drops an entire frontend app.
     Cool for even removing the application from our context settings';

    protected function configure(): void
    {
        $this->setName($this->name)
            ->setAliases(['f:d'])
            ->setDescription($this->description);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fs = new Filesystem();

        $settings = pionia::getSetting("frontend") ?? [];

        $path = isset($settings['frontend_root_folder']) ? BASEPATH."/".$settings['frontend_root_folder']: null;

        if (!$path) {
            $path = BASEPATH . "/" . $this->directory;
        }

        if (!is_dir($path)) {
            $io->info("The frontend directory is undefined");
            return self::FAILURE;
        }

        $shouldNotMaintain = $io->confirm("Are you sure your want to remove the entire $path? Action is irreversible! 𐐘💥╾━╤デ╦︻ඞා", "yes");

        if ($shouldNotMaintain){
            $fs->remove($path);
            Utilities::inidelsection("frontend");
            pionia::boot();
            $io->info("Frontend dropped successfully!");
            return self::SUCCESS;
        }

        $io->info("Pionia aborting this action!");
        return self::FAILURE;
    }

}
