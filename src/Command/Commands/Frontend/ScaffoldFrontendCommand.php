<?php

namespace Pionia\Command\Commands\Frontend;

use Pionia\Command\BaseCommand;
use Pionia\Core\Helpers\Utilities;
use Pionia\Core\Pionia;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Scaffolds any frontend project in the right directory using a package manage of choice and a frontend framework of choice.
 *
 * Command is mostly interactive
 *
 * @since 1.1.6
 */
class ScaffoldFrontendCommand extends BaseCommand
{
    protected array $frameworks = ['Vue', 'React', 'Z-js', 'Qwik', 'Lit', 'Preact', 'Svelte', 'Solid'];
    protected array $pManagers = ['yarn', 'npm', 'pnpm', 'bun'];
    protected string $packageManager ='npm';
    protected string $directory = 'frontend';
    protected string $name = 'frontend:scaffold';
    protected string $description = 'Scaffolds any frontend project in the right directory 
    using a package manage of choice and a frontend framework of choice. Command is mostly interactive..';

    protected function configure(): void
    {
        $this->setName($this->name)
            ->setDescription($this->description)
            ->setAliases(['f:s', 'frontend:s'])
            ->addOption("yes", "y", InputOption::VALUE_NONE, "Whether to scaffold with the defaults throughout");
    }

    protected function askFrameworkOptions($input, $output, ?bool $shouldYes = false): bool | string
    {
        $io = new SymfonyStyle($input, $output);

        // we choose the framework here and if none is defined, we choose vue
        $framework = 'Vue';
        if (!$shouldYes) {
            $framework = $io->choice("Choose the framework to scaffold", $this->frameworks, $framework);
            if ($framework === 'Quit'){
                $io->info("Pionia aborting scaffolding process now");
                return false;
            }
        }

        $confirmed = $shouldYes;
        if (!$shouldYes) {
            $confirmed = $io->confirm("You are about to scaffold a " . $framework . " project, are you sure?");
        }

        if (!$confirmed && !$shouldYes) {
            if (!in_array('Quit', $this->frameworks)) {
                $this->frameworks[] = 'Quit';
            }
            return $this->askFrameworkOptions($input, $output, $shouldYes);
        }
        return $framework;
    }

    /**
     * Scaffolds a full frontend framework
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $shouldYes = $input->getOption("yes");

        $io = new SymfonyStyle($input, $output);

        if ($shouldYes){
            $io->info("Proceeding with the defaults...");
        }

        $fs = new Filesystem();

        $framework = $this->askFrameworkOptions($input, $output, $shouldYes);

        if (!$framework){
            return self::FAILURE;
        }

        $io->note("By default, Pionia creates frontend directory as 'frontend' in the root directory.\n
         You can only change the name of the directory.");
        if (!$shouldYes) {
            $shouldMaintain = $io->confirm("Do you want to maintain 'frontend' as the directory name of your frontend?");
            if (!$shouldMaintain) {
                $this->directory = $io->ask("Which name do you want for your frontend directory?", $this->directory);
            }

            if (str_contains("/", $this->directory)){
                $io->error("Project name should not define sub-directories(/) e.g. frontend");
                return self::FAILURE;
            }
        }

        $path = BASEPATH."/".$this->directory;

        if ($fs->exists($path)) {
            $io->error("Project directory with the name $this->directory already exists.");
            return self::FAILURE;
        }

        if (!$shouldYes) {
            $this->packageManager = $io->choice("Select your favorite package manager(should already be installed)",
                $this->pManagers, $this->packageManager);
        }
        $result = null;

        $installCommand = $this->packageManager." install";

        $progress = $io->createProgressBar(14);

        if ($framework === "Z-js") {
            $io->info("Scaffolding your app in ".$path);
            exec(" cd  ".BASEPATH." && npx create-z-project ".$this->directory." -y -f && ".$installCommand, $result);

        } else {

            // the rest of the frameworks below here can work with both ts and js templates using vite
            $askTemplateToUse = $framework;
            if (!$shouldYes) {
                $askTemplateToUse = $io->choice("Which of the template scaffolds should we proceed with", [$framework, $framework . "-ts"], $framework);
            }

            $finalTemplate = strtolower($askTemplateToUse);

            $io->info("Scaffolding your app in ".$path);

            $command = "npm create vite@latest ".$this->directory." -- --template ".$finalTemplate." -y";

            if ($this->packageManager !== 'npm'){
                $command = $this->packageManager." create vite ".$this->directory." --template ".$finalTemplate." -y";
            }

            exec(" cd  ".BASEPATH." &&".$command, $result);
        }
        $progress->advance(2);

        $io->info("Installing dependencies...");

        shell_exec(" cd  ".$path." && ".$installCommand);

        // z-js does tends to have a package-lock.json file, drop it or anything else that might be in the way
        $fs->remove(BASEPATH."/package-lock.json");
        $fs->remove(BASEPATH."/yarn.lock");
        $fs->remove(BASEPATH."/node_modules");

        $progress->advance(4);

        $io->info("Modules installation completed");

        $io->title("Frontend Environment Setup");

        $fs = new Filesystem();

        $prodEnv = $framework == 'Z-js' ? $path."/.env.production" : $path."/src/.env.production";
        $devEnv = $framework == 'Z-js' ? $path."/.env.development" : $path."/src/.env.development";

        $io->info("Creating the production env file at ".$prodEnv);
        $fs->touch($prodEnv);
        $progress->advance(2);

        $io->info("Creating development env file at ".$devEnv);
        $fs->touch($devEnv);
        $progress->advance(2);

        $serverSettings = pionia::getServerSettings();

        $port = $serverSettings['PORT']?? $serverSettings['port'] ?? 8000;

        $io->info("Adding `VITE_API_URL` key that points to the backend at port ".$port);
        $io->note("If your port is not set to ".$port.", remember to change this in the development env file at $devEnv.
         Production file does not rely on the port at all since it will be served on root of your Pionia backend.");
        file_put_contents($devEnv, "# .env.development\nVITE_API_URL=http://localhost:$port/api/");

        $progress->advance(2);

        file_put_contents($prodEnv, "# .env.production\nVITE_API_URL=/api/");
        $io->info("Setting up your frontend environment variables complete");
        $progress->advance(2);

        $io->title("Pionia Settings Setup");

        $buildCommand = $this->packageManager;

        if ($buildCommand === 'npm') {
            $buildCommand .= ' run ';
        }

        $buildCommand .= ' build ';

        Utilities::updateSettingsFileSection("frontend", [
            "frontend_root_folder"=> $this->directory,
            "build_command"=> $buildCommand,
            "frontend_build_folder"=> "dist",
            "frontend_framework"=> $framework,
            "package_manager"=> $this->packageManager
        ]);

        $io->info("Added the `frontend` section in the database.ini");

        $progress->finish();

        $io->success("Frontend scaffolding completed successfully");
        return self::SUCCESS;
    }
}
