<?php

namespace Pionia\Builtins\Commands;

use Pionia\Console\BaseCommand;
use Pionia\Utils\InteractsWithTime;
use Symfony\Component\Console\Input\InputOption;

/**
 * For starting the command line server. This should be good choice only in development
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class StartServer extends BaseCommand
{
    use InteractsWithTime;

    protected array $aliases = ['server', 'start', 'run', 'serve'];

    /**
     * The name of the command.
     *
     * @var string
     */
    protected string $name = 'serve';

    /**
     * The name of the command.
     *
     * @var string
     */
    protected string $description = 'Serve the application on the PHP development server';

    /**
     * The help message of the command.
     *
     * @var string
     */
    protected string $help = 'This command starts the Pionia server. It should be preferred for localhost development only';

    /**
     * The current port offset.
     *
     * @var int
     */
    protected int $portOffset = 0;

    /**
     * Number of times to re-attempt a lost connection
     * @var int
     */
    protected int $reattempts = 0;

    /**
     * The port on which to run the server.
     * If not passed thru the args, will find it in the settings, otherwise  will fallback to 8000
     * @return int
     */
    private function port(): int
    {
        $port = $this->option('port');

        if (!$port) {
            $port = env('SERVER_PORT', 8000);
        }
        setEnv('SERVER_PORT', $port);
        return (int) $port;
    }

    protected function handle(?int $port = null): int
    {
        $this->reattempts = $this->option('tries') ?? 0;
        $port = $port ?? $this->port();
        $host = $this->host();
        $this->output->writeln("Starting ".$this->getApp()->appName." on http://" .$host.':'.$port);
        $this->output->writeln('Press Ctrl+C to stop the server');
        $output = shell_exec(implode(' ', $this->serverCommand($port, $host)));

        if (!$output && $this->reattempts > 0) {
            $this->reattempts -= $this->reattempts;
            $this->portOffset += 1;
            $newPort = $port+ $this->portOffset;
            $this->getApp()->logger->info('Server failed to start on port '.$port.'. Trying port '.$newPort.'...');
            return $this->handle($newPort);
        }

        return self::SUCCESS;
    }
    /**
     * Get the full server command.
     *
     * @param $port
     * @param $host
     * @return array
     */
    protected function serverCommand($port, $host): array
    {
        $server = file_exists(alias(\DIRECTORIES::PUBLIC_DIR->name).'/index.php')
            ? alias(\DIRECTORIES::PUBLIC_DIR->name).'/index.php'
            : __DIR__.'example/public/index.php';
        return [
            $this->getApp()->phpPath(),
            '-S',
            $host.':'.$port,
            $server,
        ];
    }


    /**
     * Get the host and port from the host option string.
     *
     * @return array
     */
    protected function getHostAndPort(): array
    {
        if (preg_match('/(\[.*\]):?([0-9]+)?/', $this->input->getOption('host'), $matches) !== false) {
            return [
                $matches[1] ?? $this->input->getOption('host'),
                $matches[2] ?? null,
            ];
        }

        $hostParts = explode(':', $this->input->getOption('host'));

        return [
            $hostParts[0],
            $hostParts[1] ?? null,
        ];
    }

    /**
     * Get the host for the command.
     *
     * @return string
     */
    protected function host(): string
    {
        [$host] = $this->getHostAndPort();

        return $host;
    }
    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', env('host', '127.0.0.1')],
            ['port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on', env('port')],
            ['tries', null, InputOption::VALUE_OPTIONAL, 'The max number of ports to attempt to serve from', 10],
            ['no-reload', null, InputOption::VALUE_NONE, 'Do not reload the development server on .env file changes'],
        ];
    }
}
