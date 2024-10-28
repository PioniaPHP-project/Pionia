<?php

namespace Pionia\Command\Commands\Generators;

use Pionia\Codegens\AuthBackend;
use Pionia\Command\BaseCommand;
use Pionia\Core\Pionia;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * For Creating a new authentication backend in the authenticationBackends directory by running `pionia gen:auth {name}`
 *
 * @since 1.1.6 This command now writes the generated file to the `authentications` directory in your app
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CreateAuthenticationBackend extends BaseCommand
{
    protected static string $title = 'Adds a new authentication backend';
    protected static string $description = 'Generates an authentication backend for pionia app.';
    protected static string $name = 'gen:auth';

    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setAliases(['g:a'])
            ->setDescription('Creates a '.pionia::$name.' authentication backend')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the authentication backend')
            ->setHelp('Create a new authentication backend of a '.pionia::$name.' application in app/authenticationBackends');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service_name = $input->getArgument('name');

        $io = new SymfonyStyle($input, $output);
        $io->info("Generating $service_name...");

        $service = new AuthBackend($service_name, $output);
        $service->generate(null, $io);

        return Command::SUCCESS;
    }
}
