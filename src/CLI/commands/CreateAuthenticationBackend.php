<?php

namespace Pionia\CLI\commands;

use Pionia\codegens\AuthBackend;
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
class CreateAuthenticationBackend extends BaseCommand
{
    protected static string $title = 'Adds a new authentication backend';
    protected static string $description = 'add authentication backend';
    protected static string $name = 'addauth';
    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setDescription('Creates a '.$this::base()::$name.' authentication backend')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the authentication backend')
            ->setHelp('Create a new authentication backend of a '.$this::base()::$name.' application in app/authenticationBackends');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service_name = $input->getArgument('name');

        $output->writeln("Generating $service_name...");

        $service = new AuthBackend($service_name, $output);
        $service->generate();

        return Command::SUCCESS;
    }
}
