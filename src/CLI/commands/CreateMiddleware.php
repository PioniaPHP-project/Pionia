<?php

namespace Pionia\CLI\commands;

use Pionia\codegens\AuthBackend;
use Pionia\codegens\Middleware;
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
class CreateMiddleware extends BaseCommand
{
    protected static string $title = 'Adds a new middleware to pionia app';
    protected static string $description = 'add middleware backend';
    protected static string $name = 'addmidd';
    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setDescription('Creates a '.$this::base()::$name.' middleware')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the meddleware to create')
            ->setHelp('Create a new middleware of a '.$this::base()::$name.' application in app/middlewares');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service_name = $input->getArgument('name');

        $output->writeln("Generating $service_name...");

        $service = new Middleware($service_name, $output);
        $service->generate();

        return Command::SUCCESS;
    }
}
