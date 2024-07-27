<?php

namespace application\commands;

use Pionia\Command\BaseCommand;
use Pionia\Core\Pionia;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Drops an entire frontend
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
            self::inidelsection("frontend");
            pionia::boot();

            return self::SUCCESS;
        }

        $io->info("Pionia aborting this action!");
        return self::FAILURE;
    }


    /**
     * Removes an entire section from the settings.ini file
     * @param string $section
     * @return void
     */
    public static function inidelsection(string $section): void
    {
        $parsed_ini = parse_ini_file(SETTINGS, TRUE);
        $skip = "$section";
        $output = '';
        foreach ( $parsed_ini as $section=>$info ) {
            if ( $section != $skip ) {
                $output .= "[$section]\n";
                foreach ( $info as $var=>$val ) {
                    $output .= "$var=$val\n";
                }
                $output .= "\n\n";
            }
        }
        $file_resource = fopen(SETTINGS, 'w+');
        fwrite($file_resource, "$output");
        fclose($file_resource);
        pionia::boot();
    }

}