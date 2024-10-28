<?php

namespace Pionia\Command\Commands\Generators;

use Pionia\Codegens\GenerateSwitch;
use Pionia\Command\BaseCommand;
use Pionia\Core\Pionia;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates a new Pionia Switch in  Switches directory by running `pionia gen:switch {version}`
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CreateSwitch extends BaseCommand
{
    protected static string $title = 'Adds a new switch to pionia app';
    protected static string $description = 'Generates a switch for a pionia app. Switches map requests to services.';
    protected static string $name = 'gen:switch';
    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setAliases(['g:sw', 'sw:on', 'switch:on'])
            ->setDescription('Creates a '.pionia::$name.' switch')
            ->addArgument('version', InputArgument::REQUIRED, 'The version of the switch to create, this is where the switch will be created')
            ->setHelp('Create a new switch of a '.pionia::$name.' application in app/switches');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service_name = $input->getArgument('version');

        $io = new SymfonyStyle($input, $output);

        $io->info("Generating $service_name service...");

        $service = new GenerateSwitch($service_name, $output);

        $service->generate(null, $io);

        return Command::SUCCESS;
    }
}
