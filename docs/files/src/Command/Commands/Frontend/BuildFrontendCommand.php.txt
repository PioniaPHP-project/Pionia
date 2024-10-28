<?php

namespace Pionia\Command\Commands\Frontend;

use Nette\Utils\Finder;
use Pionia\Command\BaseCommand;
use Pionia\Core\Pionia;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Throwable;

/**
 * Builds and hosts the frontend in our app server for production
 *
 * @since 1.1.6
 */
class BuildFrontendCommand extends BaseCommand
{
    protected string $name = 'frontend:build';
    protected string $description = 'Prepares, builds and serves the frontend build files in the Pionia server';

    protected function configure(): void
    {
        $this->setName($this->name)
            ->setAliases(['f:b', 'f:build', 'frontend:build', 'frontend:b' , 'front:build'])
            ->setDescription($this->description);
    }

    /**
     * Scaffolds a full frontend framework
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fs = new Filesystem();

        $settings = pionia::getSetting("frontend") ?? null;

        if (!$settings){
            $io->error("No settings detected for frontend build");
            return self::FAILURE;
        }

        $dir = $settings['frontend_root_folder'] ?? null;

        if (!$dir || !$fs->exists(BASEPATH."/".$dir)){
            $io->error("No frontend app directory detected");
        }

        $path = BASEPATH."/".$dir;

        $buildCommand = $settings['build_command'] ?? 'vite build';

        $buildFolder = $settings['frontend_build_folder'] ?? 'build';

        $buildPath = $path."/".$buildFolder."/";

        if ($fs->exists($buildPath)){
            $fs->remove($buildPath);
        }

        $io->info("Running the build command $buildCommand in $dir");

        $result = null;

        exec("cd ".$path." && ".$buildCommand, $result);

        if (!$fs->exists($buildPath)){
            $io->error("The Build path $buildPath does not exist even after building, are you sure you're defining it right in your database.ini?");
            return self::FAILURE;
        }

        $fs->mirror($buildPath, BASEPATH);

        file_put_contents(BASEPATH."/manifest.json", self::returnAllFilesInDir($buildPath));

        $io->success("Build files successfully served!");
        return self::SUCCESS;
    }


    public static function returnAllFilesInDir(string $dir): string
    {
        $arr = [];
        foreach (Finder::findFiles()->from($dir) as $file) {
            $arr[$file->getFilename()] = [
                'file' => $file->getRelativePathname(),
                'dir' => $file->getRelativePath(),
            ];
        }
        return json_encode($arr);
    }

}
