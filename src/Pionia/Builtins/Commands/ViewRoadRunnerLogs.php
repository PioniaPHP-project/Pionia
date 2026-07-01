<?php

namespace Pionia\Builtins\Commands;

use Pionia\Builtins\Commands\Concerns\FormatsRoadRunnerLogOutput;
use Pionia\Builtins\Commands\Concerns\ManagesRoadRunnerProcess;
use Pionia\Console\BaseCommand;
use Pionia\Console\Command;
use Pionia\Console\Input\InputOption;

/**
 * Tail RoadRunner logs in real time (same file as runserver --detach).
 */
class ViewRoadRunnerLogs extends BaseCommand
{
    use FormatsRoadRunnerLogOutput;
    use ManagesRoadRunnerProcess;

    protected string $name = 'runserver:logs';

    protected array $aliases = ['rr:logs', 'roadrunner:logs', 'logs:rr'];

    protected string $description = 'Follow RoadRunner logs in real time';

    protected string $help = 'Tails storage/logs/roadrunner.log. Use after starting with runserver --detach. Press Ctrl+C to stop.';

    protected function getOptions(): array
    {
        return [
            ['log', null, InputOption::VALUE_OPTIONAL, 'Path to the RoadRunner log file', null],
            ['lines', null, InputOption::VALUE_OPTIONAL, 'Number of existing lines to show before following', '50'],
            ['no-follow', null, InputOption::VALUE_NONE, 'Print lines and exit without following'],
            ['wait', 'w', InputOption::VALUE_NONE, 'Wait for the log file to be created'],
            ['raw', null, InputOption::VALUE_NONE, 'Print log lines without formatting'],
        ];
    }

    protected function handle(): int
    {
        $path = $this->resolveRoadRunnerLogPath($this->option('log'));
        $lines = max(0, (int) $this->option('lines'));
        $follow = !$this->option('no-follow');

        if (!$this->waitForLogFile($path, (bool) $this->option('wait'))) {
            $this->error("Log file not found: {$path}");
            $this->line('Start RoadRunner in the background: php pionia runserver --detach');
            $this->line('Or pass --wait to block until the log file exists.');

            return Command::FAILURE;
        }

        $this->comment("Following {$path}" . ($follow ? ' (Ctrl+C to stop)' : ''));
        $offset = $this->printLastLines($path, $lines);

        if (!$follow) {
            return Command::SUCCESS;
        }

        $this->followFromOffset($path, $offset);

        return Command::SUCCESS;
    }

    private function waitForLogFile(string $path, bool $wait): bool
    {
        if (is_file($path)) {
            return true;
        }

        if (!$wait) {
            return false;
        }

        $this->comment('Waiting for log file...');
        while (!is_file($path)) {
            usleep(200_000);
        }

        return true;
    }

    private function printLastLines(string $path, int $lineCount): int
    {
        if ($lineCount <= 0) {
            return (int) (filesize($path) ?: 0);
        }

        $content = file($path, FILE_IGNORE_NEW_LINES);
        if ($content === false) {
            return 0;
        }

        foreach (array_slice($content, -$lineCount) as $line) {
            $this->roadRunnerLogWriter()->push($line . "\n");
        }
        $this->flushRoadRunnerLogWriter();

        return (int) (filesize($path) ?: 0);
    }

    private function followFromOffset(string $path, int $offset): void
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            $this->error("Unable to open log file: {$path}");

            return;
        }

        fseek($handle, $offset);
        $inode = @fileinode($path);

        while (true) {
            $line = fgets($handle);
            if ($line !== false) {
                $this->writeRoadRunnerLogChunk($line);

                continue;
            }

            usleep(200_000);
            clearstatcache(true, $path);

            if (!is_file($path)) {
                while (!is_file($path)) {
                    usleep(200_000);
                }

                fclose($handle);
                $handle = fopen($path, 'rb');
                if ($handle === false) {
                    return;
                }

                $inode = @fileinode($path);

                continue;
            }

            $currentInode = @fileinode($path);
            if ($inode !== false && $currentInode !== false && $currentInode !== $inode) {
                fclose($handle);
                $handle = fopen($path, 'rb');
                if ($handle === false) {
                    return;
                }

                $inode = $currentInode;

                continue;
            }

            $size = filesize($path);
            if ($size !== false && $size < ftell($handle)) {
                fseek($handle, 0);
            }
        }
    }
}
