<?php

namespace jetPhp\command\commands;

use jetPhp\command\BaseCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * For starting the command line server. This should be good choice only in development
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class StartServer extends BaseCommand
{
    protected static $title = 'Start Server';
    protected static $description = 'serve';
    protected static $name = 'serve';

    private array $command = ['php', '-S'];

    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setDescription('Starts the '.$this::base()::$name.' server')
            ->addOption('port', 'p', InputArgument::OPTIONAL, 8000)
            ->setHelp('This command starts the '.$this::base()::$name.' server. It should be preferred for localhost development only')
            ->addOption('host', null, InputArgument::OPTIONAL, 'localhost');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = $input->getOption('port') ?? 8000;
        $host = $input->getOption('host') ?? 'localhost';
        $output->writeln('Starting server on http://' .$host.':'.$port);
        $output->writeln('Press Ctrl+C to stop the server');
        // start the server
        if ($port && $host){
            $this->command[] = $host . ':' . $port;
        }

        // start the php server here
        shell_exec(implode(' ', $this->command));
        $output->writeln($this::base()::$name.' Server started at '.date('Y-m-d H:i:s'));
        return Command::SUCCESS;
    }
}
