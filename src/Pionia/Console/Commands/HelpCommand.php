<?php

namespace Pionia\Console\Commands;

use Pionia\Console\AbstractCommand;
use Pionia\Console\Input\InputArgument;
use Pionia\Console\Input\InputInterface;
use Pionia\Console\Output\OutputInterface;

final class HelpCommand extends AbstractCommand
{
    public function __construct()
    {
        parent::__construct('help');
        $this->setDescription('Display help for a command');
        $this->addArgument('command_name', InputArgument::OPTIONAL, 'The command name', 'help');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            return self::FAILURE;
        }

        $name = (string) $input->getArgument('command_name');
        if ($name === 'help' && $input->getFirstArgument() !== null) {
            $name = $input->getFirstArgument();
        }

        try {
            $command = $application->find($name);
        } catch (\InvalidArgumentException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return self::FAILURE;
        }

        $output->writeln('<info>Description:</info>');
        $output->writeln('  ' . $command->getDescription());
        $output->writeln('');
        $output->writeln('<info>Usage:</info>');
        $output->writeln('  pionia ' . $command->getName());

        if ($command->getHelp() !== '') {
            $output->writeln('');
            $output->writeln('<info>Help:</info>');
            $output->writeln('  ' . $command->getHelp());
        }

        if ($command->getAliases() !== []) {
            $output->writeln('');
            $output->writeln('<info>Aliases:</info> ' . implode(', ', $command->getAliases()));
        }

        return self::SUCCESS;
    }
}
