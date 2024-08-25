<?php

namespace Pionia\Pionia\Builtins\Commands;

use Carbon\Carbon;
use Exception;
use Pionia\Pionia\Console\BaseCommand;
use Pionia\Pionia\Utils\Arrayable;
use Pionia\Pionia\Utils\InteractsWithTime;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Terminal;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * For starting the command line server. This should be good choice only in development
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
#[AsCommand(name: 'serve')]
class StartServer extends BaseCommand
{
    use InteractsWithTime;

    protected string $description = 'Serve the application on the PHP development server';

    protected string $help = 'This command starts the Pionia server. It should be preferred for localhost development only';

    /**
     * The current port offset.
     *
     * @var int
     */
    protected int $portOffset = 0;

    protected \Pionia\Pionia\Utils\Carbon $requestsPool;

    /**
     * The list of lines that are pending to be output.
     *
     * @var string
     */
    protected string $outputBuffer = '';

    public static array $passthroughVariables = [
        'APP_ENV',
        'HERD_PHP_81_INI_SCAN_DIR',
        'HERD_PHP_82_INI_SCAN_DIR',
        'HERD_PHP_83_INI_SCAN_DIR',
        'IGNITION_LOCAL_SITES_PATH',
        'PATH',
        'PHP_CLI_SERVER_WORKERS',
        'PHP_IDE_CONFIG',
        'SYSTEMROOT',
        'XDEBUG_CONFIG',
        'XDEBUG_MODE',
        'XDEBUG_SESSION',
    ];

    protected bool $serverRunningHasBeenDisplayed = false;

    protected bool $isolated = true;

//    private array $command = ['php', '-S'];

    private function port(): int
    {
        $port = $this->option('port');

        if (!$port) {
            $port = $this->getApp()->env->has('port') && $this->getApp()->env->get('port');
            if (!$port) {
                $env = $this->getApp()->getSilently('env');
                if ($env) {
                    if ($env->has('port')) {
                        $port = $env->get('port');
                    } else if ($env->has('server')) {
                        $server = arr($env->get('server'));
                        if ($server->has('port')) {
                            $port = $server->get('port');
                        }
                    }
                }
            }
        }
        if (!$port) {
            $port = 8000;
        }
        $this->getApp()->addEnv('port', $port);
        return (int) $port;
    }

    protected function handle(): int
    {
        $port = $this->port();
        $host = $this->host();
        $this->output->writeln("Starting ".$this->getApp()->appName." on http://" .$host.':'.$port);
        $this->output->writeln('Press Ctrl+C to stop the server');
        shell_exec(implode(' ', $this->serverCommand($port, $host)));
        $this->output->writeln($this->getApp()->appName.' Server started at '.Carbon::now());
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
        $server = file_exists($this->getApp()->appRoot('example/public/index.php'))
            ? $this->getApp()->appRoot('example/public/index.php')
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
     * Check if the command has reached its maximum number of port tries.
     *
     * @return bool
     */
    protected function canTryAnotherPort(): bool
    {
        return is_null($this->input->getOption('port')) &&
            ($this->input->getOption('tries') > $this->portOffset);
    }

    /**
     * Returns a "callable" to handle the process output.
     *
     * @return callable(string, string): void
     */
    protected function handleProcessOutput(): callable
    {
        return function ($type, $buffer) {
            $this->outputBuffer .= $buffer;

            $this->flushOutputBuffer();
        };
    }

    /**
     * Flush the output buffer.
     *
     * @return void
     * @throws Exception
     */
    protected function flushOutputBuffer(): void
    {
        $lines = Arrayable::toArrayable(explode("\n", ($this->outputBuffer)));

        $this->outputBuffer = (string)$lines->pop();

        $lines
            ->map(fn($line) => trim($line))
            ->filter()
            ->each(function ($line) {
                if (str_contains($line, 'Development Server (http')) {
                    if ($this->serverRunningHasBeenDisplayed === false) {
                        $this->serverRunningHasBeenDisplayed = true;

                        $this->getApp()->logger?->info("Server running on [http://{$this->host()}:{$this->port()}].");
                        $this->comment('  <fg=yellow;options=bold>Press Ctrl+C to stop the server</>');

                        $this->newLine();
                    }

                    return;
                }

                if (str_contains($line, ' Accepted')) {
                    $requestPort = $this->getRequestPortFromLine($line);

                    $this->requestsPool[$requestPort] = [
                        $this->getDateFromLine($line),
                        $this->requestsPool[$requestPort][1] ?? false,
                        microtime(true),
                    ];
                } elseif (str_contains($line, ' [200]: GET ')) {
                    $requestPort = $this->getRequestPortFromLine($line);

                    $this->requestsPool[$requestPort][1] = trim(explode('[200]: GET', $line)[1]);
                } elseif (str_contains($line, 'URI:')) {
                    $requestPort = $this->getRequestPortFromLine($line);

                    $this->requestsPool[$requestPort][1] = trim(explode('URI: ', $line)[1]);
                } elseif (str_contains($line, ' Closing')) {
                    $requestPort = $this->getRequestPortFromLine($line);

                    if (empty($this->requestsPool[$requestPort])) {
                        $this->requestsPool[$requestPort] = [
                            $this->getDateFromLine($line),
                            false,
                            microtime(true),
                        ];
                    }

                    [$startDate, $file, $startMicrotime] = $this->requestsPool[$requestPort];

                    $formattedStartedAt = $startDate->format('Y-m-d H:i:s');

                    unset($this->requestsPool[$requestPort]);

                    [$date, $time] = explode(' ', $formattedStartedAt);

                    $this->output->write("  <fg=gray>$date</> $time");

                    $runTime = $this->runTimeForHumans($startMicrotime);

                    if ($file) {
                        $this->output->write($file = " $file");
                    }

                    $dots = max((new Terminal())->getWidth() - mb_strlen($formattedStartedAt) - mb_strlen($file) - mb_strlen($runTime) - 9, 0);

                    $this->output->write(' ' . str_repeat('<fg=gray>.</>', $dots));
                    $this->output->writeln(" <fg=gray>~ {$runTime}</>");
                } elseif (str_contains($line, 'Closed without sending a request') || str_contains($line, 'Failed to poll event')) {
                    // ...
                } elseif (!empty($line)) {
                    if (str_starts_with($line, '[')) {
                        $line = str_split('] ')[1];
                    }

                    $this->output->writeln("  <fg=gray>$line</>");
                }
            });
    }

    /**
     * Get the request port from the given PHP server output.
     *
     * @param string $line
     * @return int
     */
    protected function getRequestPortFromLine(string $line): int
    {
        preg_match('/:(\d+)\s(?:(?:\w+$)|(?:\[.*))/', $line, $matches);

        return (int) $matches[1];
    }

    /**
     * Get the date from the given PHP server output.
     *
     * @param string $line
     * @return Carbon
     */
    protected function getDateFromLine(string $line): Carbon
    {
        $regex = $this->getApp()->getEnv()->get('PHP_CLI_SERVER_WORKERS', 1) > 1
            ? '/^\[\d+]\s\[([a-zA-Z0-9: ]+)\]/'
            : '/^\[([^\]]+)\]/';

        $line = str_replace('  ', ' ', $line);

        preg_match($regex, $line, $matches);

        return Carbon::createFromFormat('D M d H:i:s Y', $matches[1]);
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions(): array
    {
        return [
            ['host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', $this->getApp()->getOrDefault('SERVER_HOST', '127.0.0.1')],
            ['port', null, InputOption::VALUE_OPTIONAL, 'The port to serve the application on', $this->getApp()->getSilently('SERVER_PORT')],
            ['tries', null, InputOption::VALUE_OPTIONAL, 'The max number of ports to attempt to serve from', 10],
            ['no-reload', null, InputOption::VALUE_NONE, 'Do not reload the development server on .env file changes'],
        ];
    }
}
