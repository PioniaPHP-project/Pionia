<?php

namespace Pionia\CLI\commands;

use Pionia\codegens\Service;
use Pionia\CLI\BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * For Creating a new service
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CreateService extends BaseCommand
{
    protected static string $title = 'Add a new service';
    protected static string $description = 'add service';
    protected static string $name = 'addservice';
    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setDescription('Creates a '.$this::base()::$name.' service')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the service')
            ->addArgument('actions',  InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Service names that should be added by default')
            ->setHelp('This command creates a new file in the services folder of a '.$this::base()::$name.' application');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service_name = $input->getArgument('name');
        $actions = $input->getArgument('actions');

        $output->writeln("Generating $service_name...");

        $service = new Service($service_name, $actions, $output);
        $service->generate();

        return Command::SUCCESS;
    }
}
