<?php

namespace Pionia\CLI\commands;

use Pionia\CLI\BaseCommand;
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
    protected static string $title = 'Start Server';
    protected static string $description = 'serve';
    protected static string $name = 'serve';

    private array $command = ['php', '-S'];

    private function port()
    {
        $port = 8000;
        $server = $this->getServerSettings();
        if (count($server) > 0) {
            if (array_key_exists('port', $server)) {
                $port = $server['port'];
            }
        }
        return $port;
    }

    protected function configure(): void
    {
        $this
            ->setName($this::$name)
            ->setDescription('Starts the '.$this::base()::$name.' server')
            ->addOption('port', 'p', InputArgument::OPTIONAL, $this->port())
            ->setHelp('This command starts the '.$this::base()::$name.' server. It should be preferred for localhost development only')
            ->addOption('host', null, InputArgument::OPTIONAL, 'localhost');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $port = $input->getOption('port') ?? $this->port();
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
