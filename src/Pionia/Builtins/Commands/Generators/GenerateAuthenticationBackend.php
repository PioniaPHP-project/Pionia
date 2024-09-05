<?php

namespace Pionia\Pionia\Builtins\Commands\Generators;

use Pionia\Pionia\Console\BaseCommand;
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
    protected string $title = 'Adds a new authentication backend';
    protected  string $help = 'Generates an authentication backend for pionia app.';
    protected string $description = 'Generates an authentication backend for pionia app.';
    protected string $name = 'gen:auth';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service_name = $input->getArgument('name');

        $io = new SymfonyStyle($input, $output);
        $io->info("Generating $service_name...");

//        $service = new AuthBackend($service_name, $output);
//        $service->generate(null, $io);

        return Command::SUCCESS;
    }
}
