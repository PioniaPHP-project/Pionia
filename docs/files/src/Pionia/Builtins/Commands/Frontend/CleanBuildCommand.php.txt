<?php

namespace Pionia\Command\Commands\Frontend;

use Pionia\Command\BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Cleans all build files that were formerly added via building.
 *
 * @since 1.1.6
 * */
class CleanBuildCommand extends BaseCommand
{
    protected string $directory = 'frontend';
    protected string $name = 'frontend:build:clean';
    protected string $description = 'Cleans out all frontend build files that were previously added to the backend for serving!';

    protected function configure(): void
    {
        $this->setName($this->name)
            ->setAliases(['f:bc'])
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

        $manifest = BASEPATH."/manifest.json";

        if ($fs->exists($manifest)) {
            $contents = trim($fs->readFile($manifest));
        } else {
            $io->error("Could not find manifest.json, usually this error is raised when you attempt to clean the build files before building at all. Have you built yet? 🤯 (๑>◡<๑)");
            return self::FAILURE;
        }

        $contents  = json_decode($contents, true);

        if (count($contents) > 0) {
            foreach ($contents as $content) {
                $file = $content['file'];
                $dir = $content['dir'];

                $io->info("Cleaning $file ⛔");
                if (!empty($dir)){
                    $fs->remove(BASEPATH.'/'.$dir);
                } else {
                    $fs->remove(BASEPATH . '/' . $file);
                }
            }
        }

        $fs->remove($manifest);

        $io->success("Build files cleaned successfully! 🧹🧼");

        return self::SUCCESS;
    }


}
