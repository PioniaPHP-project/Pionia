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
class GenerateAuthenticationBackend extends BaseCommand
{
    protected string $title = 'Adds a new authentication backend';
    protected  string $help = 'Generates an authentication backend for pionia app.';
    protected string $description = 'Generates an authentication backend for pionia app.';
    protected string $name = 'make:auth';
    protected array $aliases = ['g:a', 'gen:auth'];

    public function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the authentication backend to generate'],
        ];
    }


    protected function handle(): int
    {
        $service_name = $this->argument('name');

//        $io = new SymfonyStyle($input, $output);
        $this->info("Generating $service_name...");
# TODO start creating the authentication backend
//        $service = new AuthBackend($service_name, $output);
//        $service->generate(null, $io);

        return Command::SUCCESS;
    }
}
