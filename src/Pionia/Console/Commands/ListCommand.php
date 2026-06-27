<?php

namespace Pionia\Console\Commands;

use Pionia\Console\Command;
use Pionia\Console\Input\InputInterface;
use Pionia\Console\Output\OutputInterface;

final class ListCommand extends Command
{
    public function __construct()
    {
        parent::__construct('list');
        $this->setDescription('List available commands');
        $this->setAliases(['ls']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            return self::FAILURE;
        }

        $namespaces = [];
        foreach ($application->all() as $command) {
            if ($command->isHidden() || in_array($command->getName(), ['list', 'help'], true)) {
                continue;
            }

            $name = $command->getName();
            $prefix = str_contains($name, ':') ? explode(':', $name, 2)[0] : '_global';
            $namespaces[$prefix][$name] = $command->getDescription();
        }

        ksort($namespaces);

        $output->writeln('<info>' . $application->getName() . '</info> ' . $application->getVersion());
        $output->writeln('');

        foreach ($namespaces as $prefix => $commands) {
            $label = $prefix === '_global' ? 'Global' : ucfirst($prefix);
            $output->writeln("<comment>{$label}</comment>");
            ksort($commands);
            foreach ($commands as $name => $description) {
                $output->writeln(sprintf('  <info>%-20s</info> %s', $name, $description));
            }
            $output->writeln('');
        }

        return self::SUCCESS;
    }
}
