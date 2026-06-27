<?php

namespace Pionia\Process;

/**
 * Lightweight subprocess runner (replaces symfony/process for Pionia CLI needs).
 */
final class Process
{
    public const OUT = 'out';

    public const ERR = 'err';

    /** @var list<string> */
    private array $command;

    private ?string $shellCommand = null;

    private ?string $cwd;

    /** @var array<string, string|false> */
    private array $env;

    private ?float $timeout;

    private bool $tty = false;

    private int $exitCode = 0;

    private string $stdout = '';

    private string $stderr = '';

    /**
     * @param list<string>|string $command
     * @param array<string, string|false>|null $env
     */
    public function __construct(
        array|string $command,
        ?string $cwd = null,
        ?array $env = null,
        mixed $input = null,
        ?float $timeout = 60,
    ) {
        $this->command = is_array($command) ? array_values($command) : [$command];
        $this->cwd = $cwd;
        $this->env = $env ?? [];
        $this->timeout = $timeout;
        unset($input);
    }

    /**
     * @param array<string, string|false>|null $env
     */
    public static function fromShellCommandline(
        string $command,
        ?string $cwd = null,
        ?array $env = null,
        mixed $input = null,
        ?float $timeout = 60,
    ): self {
        $process = new self([], $cwd, $env, $input, $timeout);
        $process->shellCommand = $command;

        return $process;
    }

    public static function isTtySupported(): bool
    {
        return PHP_OS_FAMILY !== 'Windows'
            && defined('STDIN')
            && function_exists('posix_isatty')
            && @posix_isatty(STDIN);
    }

    public function setTimeout(?float $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    public function setTty(bool $tty): static
    {
        $this->tty = $tty;

        return $this;
    }

    /**
     * @param callable(string, string): void|null $callback
     */
    public function run(?callable $callback = null): int
    {
        $this->stdout = '';
        $this->stderr = '';

        if ($this->tty && self::isTtySupported()) {
            return $this->runTty();
        }

        $command = $this->buildCommand();
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $resource = proc_open(
            $command,
            $descriptors,
            $pipes,
            $this->cwd,
            $this->buildEnvironment(),
        );

        if (!is_resource($resource)) {
            $this->exitCode = 1;
            $this->stderr = 'Failed to start process.';

            return $this->exitCode;
        }

        fclose($pipes[0]);

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $started = microtime(true);
        $open = [1 => $pipes[1], 2 => $pipes[2]];

        while ($open !== []) {
            if ($this->timeout !== null && (microtime(true) - $started) > $this->timeout) {
                $this->terminate($resource);
                $this->exitCode = 124;
                break;
            }

            $read = $open;
            $write = null;
            $except = null;

            if (@stream_select($read, $write, $except, 0, 200_000) === false) {
                break;
            }

            foreach ($read as $stream) {
                $chunk = stream_get_contents($stream);
                if ($chunk === false || $chunk === '') {
                    if (feof($stream)) {
                        $key = array_search($stream, $open, true);
                        if ($key !== false) {
                            fclose($stream);
                            unset($open[$key]);
                        }
                    }

                    continue;
                }

                if ($stream === $pipes[1]) {
                    $this->stdout .= $chunk;
                    if ($callback !== null) {
                        $callback(self::OUT, $chunk);
                    }
                } else {
                    $this->stderr .= $chunk;
                    if ($callback !== null) {
                        $callback(self::ERR, $chunk);
                    }
                }
            }

            $status = proc_get_status($resource);
            if (!$status['running']) {
                $this->exitCode = (int) $status['exitcode'];
                if ($status['signaled']) {
                    throw new ProcessSignaledException((int) $status['termsig']);
                }
                break;
            }
        }

        foreach ($open as $stream) {
            $remainder = stream_get_contents($stream);
            if ($remainder !== false && $remainder !== '') {
                if ($stream === $pipes[1]) {
                    $this->stdout .= $remainder;
                    if ($callback !== null) {
                        $callback(self::OUT, $remainder);
                    }
                } else {
                    $this->stderr .= $remainder;
                    if ($callback !== null) {
                        $callback(self::ERR, $remainder);
                    }
                }
            }
            fclose($stream);
        }

        $status = proc_get_status($resource);
        if ($status['running']) {
            proc_close($resource);
        } else {
            $this->exitCode = (int) $status['exitcode'];
            proc_close($resource);
        }

        return $this->exitCode;
    }

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    public function getExitCode(): ?int
    {
        return $this->exitCode;
    }

    public function getOutput(): string
    {
        return $this->stdout;
    }

    public function getErrorOutput(): string
    {
        return $this->stderr;
    }

    private function runTty(): int
    {
        $command = $this->buildCommand();
        $descriptors = [
            0 => ['file', '/dev/tty', 'r'],
            1 => ['file', '/dev/tty', 'w'],
            2 => ['file', '/dev/tty', 'w'],
        ];

        $resource = proc_open(
            $command,
            $descriptors,
            $pipes,
            $this->cwd,
            $this->buildEnvironment(),
        );

        if (!is_resource($resource)) {
            $this->exitCode = 1;

            return $this->exitCode;
        }

        $status = proc_get_status($resource);
        while ($status['running']) {
            usleep(50_000);
            $status = proc_get_status($resource);
            if ($status['signaled']) {
                proc_close($resource);
                throw new ProcessSignaledException((int) $status['termsig']);
            }
        }

        $this->exitCode = (int) $status['exitcode'];
        proc_close($resource);

        return $this->exitCode;
    }

    private function buildCommand(): string
    {
        if ($this->shellCommand !== null) {
            return $this->shellCommand;
        }

        return implode(' ', array_map(static fn (string $part): string => escapeshellarg($part), $this->command));
    }

    /**
     * @return array<string, string>
     */
    private function buildEnvironment(): array
    {
        $env = [];

        foreach (array_merge($_ENV, $_SERVER) as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }

            $env[$key] = (string) $value;
        }

        foreach ($this->env as $key => $value) {
            if ($value === false) {
                unset($env[$key]);
            } else {
                $env[$key] = (string) $value;
            }
        }

        return $env;
    }

    /**
     * @param resource $resource
     */
    private function terminate($resource): void
    {
        if (function_exists('posix_kill')) {
            $status = proc_get_status($resource);
            if ($status['pid'] > 0) {
                @posix_kill((int) $status['pid'], SIGTERM);
            }
        }

        proc_terminate($resource);
    }
}
