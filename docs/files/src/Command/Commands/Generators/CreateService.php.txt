<?php

namespace Pionia\Command\Commands\Generators;

use Pionia\Codegens\Service;
use Pionia\Command\BaseCommand;
use Pionia\Core\Pionia;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * For Creating a new service in the services directory by running `pionia gen:service {name}`
 *
 * @since 1.1.6 This command now supports creating generic services too!
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CreateService extends BaseCommand
{
    protected static string $title = 'Adds a new service';
    protected static string $description = 'Adds a new service to pionia app. Services are the main business logic of the application.';
    protected static string $name = 'gen:service';
    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setAliases(['g:s'])
            ->setDescription('Creates a '.pionia::$name.' service')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the service')
            ->setHelp('This command creates a new service class in the services folder of a '.pionia::$name.' application');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service_name = $input->getArgument('name');

        $io = new SymfonyStyle($input, $output);

        $actions = null;
        $targetTable = null;

        $serviceType = $io->choice("Choose the type of service to scaffold", ['Generic', 'Basic'], 'Basic');

        if ($serviceType === 'Generic') {
            $extends = $io->choice("Choose one that best satisfies your needs from below", [
                'UniversalGenericService',
                'RetrieveListUpdateDeleteService',
                'RetrieveListUpdateService',
                'RetrieveListRandomService',
                'RetrieveListDeleteService',
                'RetrieveListCreateUpdateService',
                'RetrieveListCreateService',
                'RetrieveCreateUpdateService',
                'GenericService',
            ], 'UniversalGenericService');
            $actions = [$extends];

            $io->info("You have chosen $extends as the base service");

            $targetTable = $io->ask("Please provide the target database table as is for this service");
            if (empty($targetTable)) {
                $io->error("You must provide a target table");
                return Command::FAILURE;
            }
        } else {
            $extends = $io->ask("Please comma seperated actions you want to add by default, at least one is required", 'create,update,delete,retrieve');
            if (empty($extends)) {
                $io->error("You must provide at least one action");
                return Command::FAILURE;
            }

            $actions = explode(',', $extends);
            $actions = array_map('trim', $actions);
        }

        $io->info("Generating $service_name service...");

        $service = new Service($service_name, $actions, $targetTable, $serviceType, $output);
        $service->generate(null, $io);

        return Command::SUCCESS;
    }
}
