<?php

namespace Pionia\command\commands;

use Pionia\command\BaseCommand;
use Pionia\Logging\PioniaLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * For running unit tests in the application
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class RunTests extends BaseCommand
{
    protected static string $title = 'Runs Unit tests of the application';
    protected static string $description = 'test';
    protected static string $name = 'test';

    private array $command = ['./vendor/bin/phpunit'];

    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setDescription('Runs unit tests of any '.$this::base()::$name.' application')
            ->setHelp('All unit tests to be run must be located in the tests folder in the directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $logger = PioniaLogger::init();
        $logger->info('Discovering tests');
        // start the php server here
        shell_exec(implode(' ', $this->command));
        $logger->info("All tests run successfully");
        return Command::SUCCESS;
    }
}
