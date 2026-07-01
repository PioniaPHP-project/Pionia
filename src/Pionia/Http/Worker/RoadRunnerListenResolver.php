<?php

namespace Pionia\Http\Worker;

use Pionia\Http\Server\ServerPortResolver;

/**
 * Resolves the HTTP listen address for RoadRunner.
 *
 * Precedence (highest first): CLI options, environment variables, settings.ini,
 * .rr.yaml http.address, then framework defaults (127.0.0.1:8003).
 */
final class RoadRunnerListenResolver
{
    public const DEFAULT_HOST = '127.0.0.1';

    public const DEFAULT_PORT = ServerPortResolver::DEFAULT_PORT;

    /**
     * @param array<string, mixed>|null $environmentOverride When set, used instead of env() (tests).
     */
    public function __construct(
        private readonly ?array $environmentOverride = null,
    ) {
    }

    /**
     * @return array{address: string, host: string, port: string, yaml_address: string}
     */
    public function resolve(
        string $configPath,
        ?string $cliHost = null,
        int|string|null|false $cliPort = null,
    ): array {
        $yaml = $this->parseYamlHttpAddress($configPath);
        $parsedHost = $this->parseHostOption($cliHost);

        $host = $this->resolveHost(
            $yaml['host'],
            $parsedHost['host'],
        );

        $port = $this->resolvePort(
            $yaml['port'],
            $this->cliPortOverride($cliPort, $parsedHost['port']),
        );

        $address = $host . ':' . $port;

        return [
            'address' => $address,
            'host' => $host,
            'port' => (string) $port,
            'yaml_address' => $yaml['address'],
        ];
    }

    /**
     * @return array{address: string, host: string, port: int}
     */
    private function parseYamlHttpAddress(string $configPath): array
    {
        $content = is_readable($configPath) ? file_get_contents($configPath) : false;
        $address = self::DEFAULT_HOST . ':' . self::DEFAULT_PORT;

        if (is_string($content) && preg_match('/^\s*address:\s*([^\s#"\']+)/m', $content, $matches)) {
            $address = trim($matches[1], " \t\"'");
        }

        $parsed = $this->parseHttpAddress($address);

        return [
            'address' => $parsed['address'],
            'host' => $parsed['host'],
            'port' => (int) $parsed['port'],
        ];
    }

    /**
     * @return array{address: string, host: string, port: string}
     */
    private function parseHttpAddress(string $address): array
    {
        $address = trim($address);

        if ($address === '') {
            return [
                'address' => self::DEFAULT_HOST . ':' . self::DEFAULT_PORT,
                'host' => self::DEFAULT_HOST,
                'port' => (string) self::DEFAULT_PORT,
            ];
        }

        if (str_starts_with($address, ':')) {
            $port = substr($address, 1);

            return [
                'address' => self::DEFAULT_HOST . ':' . $port,
                'host' => self::DEFAULT_HOST,
                'port' => $port,
            ];
        }

        if (!str_contains($address, ':')) {
            return [
                'address' => self::DEFAULT_HOST . ':' . $address,
                'host' => self::DEFAULT_HOST,
                'port' => $address,
            ];
        }

        [$host, $port] = explode(':', $address, 2);

        return ['address' => $host . ':' . $port, 'host' => $host, 'port' => $port];
    }

    /**
     * @return array{host: ?string, port: ?int}
     */
    private function parseHostOption(?string $hostOption): array
    {
        if (!is_string($hostOption) || $hostOption === '') {
            return ['host' => null, 'port' => null];
        }

        if (preg_match('/^(\[[^\]]+\]):?([0-9]+)?$/', $hostOption, $matches) === 1) {
            return [
                'host' => $matches[1],
                'port' => isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : null,
            ];
        }

        if (str_contains($hostOption, ':')) {
            [$host, $port] = explode(':', $hostOption, 2);

            return [
                'host' => $host !== '' ? $host : null,
                'port' => $port !== '' ? (int) $port : null,
            ];
        }

        return ['host' => $hostOption, 'port' => null];
    }

    private function cliPortOverride(int|string|null|false $cliPort, ?int $hostEmbeddedPort): ?int
    {
        if (is_scalar($cliPort) && $cliPort !== '' && $cliPort !== false) {
            return max(1, (int) $cliPort);
        }

        return $hostEmbeddedPort;
    }

    private function resolveHost(string $yamlHost, ?string $cliHost): string
    {
        if (is_string($cliHost) && $cliHost !== '') {
            return $cliHost;
        }

        $fromEnvironment = $this->hostFromEnvironment();
        if ($fromEnvironment !== null) {
            return $fromEnvironment;
        }

        return $yamlHost !== '' ? $yamlHost : self::DEFAULT_HOST;
    }

    private function resolvePort(int $yamlPort, ?int $cliPort): int
    {
        if ($cliPort !== null) {
            return max(1, $cliPort);
        }

        $fromEnvironment = $this->portFromEnvironment();
        if ($fromEnvironment !== null) {
            return $fromEnvironment;
        }

        if ($yamlPort > 0) {
            return $yamlPort;
        }

        return self::DEFAULT_PORT;
    }

    private function hostFromEnvironment(): ?string
    {
        return $this->hostFromValues($this->environmentValues());
    }

    private function portFromEnvironment(): ?int
    {
        return $this->portFromValues($this->environmentValues());
    }

    /**
     * @return array<string, mixed>
     */
    private function environmentValues(): array
    {
        if ($this->environmentOverride !== null) {
            return $this->environmentOverride;
        }

        if (!function_exists('env')) {
            return [];
        }

        $values = [];
        foreach (['PORT', 'SERVER_PORT', 'HOST', 'SERVER_HOST', 'HTTP_HOST', 'port', 'server_port', 'host', 'server_host'] as $key) {
            $value = env($key);
            if (is_scalar($value) && $value !== '') {
                $values[$key] = $value;
            }
        }

        foreach (['roadrunner', 'server'] as $section) {
            $config = env($section);
            if (is_array($config)) {
                $values[$section] = $config;
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function hostFromValues(array $values): ?string
    {
        foreach (['HOST', 'SERVER_HOST', 'HTTP_HOST', 'host', 'server_host'] as $key) {
            if (isset($values[$key]) && is_string($values[$key]) && $values[$key] !== '') {
                return $values[$key];
            }
        }

        foreach (['roadrunner', 'server'] as $section) {
            if (!isset($values[$section]) || !is_array($values[$section])) {
                continue;
            }

            foreach (['HOST', 'host'] as $key) {
                if (isset($values[$section][$key]) && is_string($values[$section][$key]) && $values[$section][$key] !== '') {
                    return $values[$section][$key];
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $values
     */
    private function portFromValues(array $values): ?int
    {
        foreach (['PORT', 'SERVER_PORT', 'port', 'server_port'] as $key) {
            if (isset($values[$key]) && is_scalar($values[$key]) && $values[$key] !== '') {
                return max(1, (int) $values[$key]);
            }
        }

        foreach (['roadrunner', 'server'] as $section) {
            if (!isset($values[$section]) || !is_array($values[$section])) {
                continue;
            }

            foreach (['PORT', 'port'] as $key) {
                if (isset($values[$section][$key]) && is_scalar($values[$section][$key]) && $values[$section][$key] !== '') {
                    return max(1, (int) $values[$section][$key]);
                }
            }
        }

        return null;
    }
}
