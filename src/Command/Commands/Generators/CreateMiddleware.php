<?php

namespace Pionia\Command\Commands\Generators;

use Pionia\Codegens\Middleware;
use Pionia\Command\BaseCommand;
use Pionia\Core\Pionia;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * For Creating a new middleware in the middlewares directory by running `pionia gen:middleware {name}`
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CreateMiddleware extends BaseCommand
{
    protected static string $title = 'Adds a new middleware to pionia app';
    protected static string $description = 'Generates a middleware for a pionia app. Middlewares run on every request and response.';
    protected static string $name = 'gen:middleware';
    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setAliases(['g:m'])
            ->setDescription('Creates a '.pionia::$name.' middleware')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the middleware to create')
            ->setHelp('Create a new middleware of a '.pionia::$name.' application in app/middlewares');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $service_name = $input->getArgument('name');

        $io = new SymfonyStyle($input, $output);

        $io->info("Generating $service_name...");

        $service = new Middleware($service_name, $output);

        $service->generate(null, $io);
        $io->success("Middleware $service->sweetName created successfully");

        return Command::SUCCESS;
    }
}
