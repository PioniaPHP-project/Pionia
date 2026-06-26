<?php

namespace Pionia\Builtins\Commands\Concerns;

use Symfony\Component\Process\Process;

trait ManagesRoadRunnerProcess
{
    /**
     * @return array{address: string, host: string, port: string}
     */
    private function resolveListenAddress(string $configPath): array
    {
        $content = is_readable($configPath) ? file_get_contents($configPath) : false;
        $address = '127.0.0.1:8080';

        if (is_string($content) && preg_match('/^\s*address:\s*([^\s#"\']+)/m', $content, $matches)) {
            $address = trim($matches[1], " \t\"'");
        }

        return $this->parseHttpAddress($address);
    }

    /**
     * @return array{address: string, host: string, port: string}
     */
    private function parseHttpAddress(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            return ['address' => '127.0.0.1:8080', 'host' => '127.0.0.1', 'port' => '8080'];
        }

        if (str_starts_with($address, ':')) {
            return ['address' => '127.0.0.1' . $address, 'host' => '127.0.0.1', 'port' => substr($address, 1)];
        }

        if (!str_contains($address, ':')) {
            return ['address' => '127.0.0.1:' . $address, 'host' => '127.0.0.1', 'port' => $address];
        }

        [$host, $port] = explode(':', $address, 2);

        return ['address' => $host . ':' . $port, 'host' => $host, 'port' => $port];
    }

    private function isPortListening(int $port): bool
    {
        return $this->listenerPidsOnPort($port) !== [];
    }

    /**
     * @return list<int>
     */
    private function listenerPidsOnPort(int $port): array
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return [];
        }

        $process = new Process(['lsof', '-nP', '-iTCP:' . $port, '-sTCP:LISTEN', '-t'], null, $this->processEnv());
        $process->run();

        if (!$process->isSuccessful()) {
            return [];
        }

        $pids = [];
        foreach (preg_split('/\s+/', trim($process->getOutput())) as $pid) {
            if ($pid !== '' && ctype_digit($pid)) {
                $pids[] = (int) $pid;
            }
        }

        return array_values(array_unique($pids));
    }

    /**
     * @param list<int> $pids
     */
    private function terminatePids(array $pids, int $signal): void
    {
        if (!function_exists('posix_kill')) {
            return;
        }

        foreach ($pids as $pid) {
            if ($pid > 0) {
                @posix_kill($pid, $signal);
            }
        }
    }

    private function stopListenersOnPort(int $port, bool $force = false): void
    {
        $pids = $this->listenerPidsOnPort($port);
        if ($pids === []) {
            return;
        }

        $this->terminatePids($pids, SIGTERM);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            if ($this->listenerPidsOnPort($port) === []) {
                return;
            }

            usleep(100_000);
        }

        if ($force) {
            $this->terminatePids($this->listenerPidsOnPort($port), SIGKILL);
        }
    }

    /**
     * @return array<string, string|false>
     */
    private function processEnv(array $extra = []): array
    {
        $env = [];

        foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (is_scalar($value)) {
                $env[$key] = (string) $value;
            } else {
                $env[$key] = false;
            }
        }

        return array_merge($env, $extra);
    }
}
