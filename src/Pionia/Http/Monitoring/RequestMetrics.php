<?php

namespace Pionia\Http\Monitoring;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\BinaryFileResponse;
use Pionia\Http\Response\Response;

/**
 * Records HTTP/API request timing and aggregates for the developer stats dashboard.
 *
 * Persists append-only JSON lines so multiple RoadRunner workers can write safely.
 */
class RequestMetrics
{
    private const int MAX_LINES = 20_000;

    private const int DEFAULT_TOP = 10;

    private const int BUFFER_FLUSH_SIZE = 25;

    /** @var list<string> */
    private static array $buffer = [];

    public static function enabled(): bool
    {
        if (defined('PIONIA_TESTING') && PIONIA_TESTING) {
            return true;
        }

        $metrics = env('metrics', []);
        if (is_array($metrics) && array_key_exists('ENABLED', $metrics)) {
            return filter_var($metrics['ENABLED'], FILTER_VALIDATE_BOOLEAN);
        }

        return apiStatsEnabled() || isDebug();
    }

    public static function record(Request $request, Response | BinaryFileResponse $response, float $durationMs): void
    {
        if (!self::enabled() || self::shouldSkip($request)) {
            return;
        }

        $endpoint = self::resolveEndpoint($request);
        $status = $response->getStatusCode();
        $line = json_encode([
            't' => microtime(true),
            'ms' => round($durationMs, 3),
            'status' => $status,
            'method' => $request->getMethod(),
            'endpoint' => $endpoint,
        ], JSON_THROW_ON_ERROR);

        self::$buffer[] = $line . "\n";

        if (count(self::$buffer) >= self::BUFFER_FLUSH_SIZE) {
            self::flush();
        }
    }

    public static function flush(): void
    {
        if (self::$buffer === []) {
            return;
        }

        self::appendLines(self::$buffer);
        self::$buffer = [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function snapshot(int $top = self::DEFAULT_TOP): array
    {
        self::flush();

        $entries = self::readEntries();
        if ($entries === []) {
            return self::emptySnapshot();
        }

        /** @var array<string, array<string, mixed>> $buckets */
        $buckets = [];
        $totalMs = 0.0;
        $startedAt = null;
        $updatedAt = null;

        foreach ($entries as $entry) {
            $key = self::bucketKey($entry['endpoint']);
            if (!isset($buckets[$key])) {
                $buckets[$key] = self::freshBucket($entry['endpoint']);
            }

            $ms = (float) ($entry['ms'] ?? 0);
            $buckets[$key]['count']++;
            $buckets[$key]['total_ms'] += $ms;
            $buckets[$key]['max_ms'] = max($buckets[$key]['max_ms'], $ms);
            $buckets[$key]['min_ms'] = min($buckets[$key]['min_ms'], $ms);

            if (($entry['status'] ?? 200) >= 400) {
                $buckets[$key]['errors']++;
            }

            $totalMs += $ms;
            $t = (float) ($entry['t'] ?? 0);
            $startedAt = $startedAt === null ? $t : min($startedAt, $t);
            $updatedAt = $updatedAt === null ? $t : max($updatedAt, $t);
        }

        $endpoints = array_values(array_map(static function (array $bucket): array {
            $count = max(1, (int) $bucket['count']);

            return [
                ...$bucket,
                'avg_ms' => round($bucket['total_ms'] / $count, 2),
                'max_ms' => round((float) $bucket['max_ms'], 2),
                'min_ms' => round((float) $bucket['min_ms'], 2),
                'total_ms' => round((float) $bucket['total_ms'], 2),
            ];
        }, $buckets));

        usort($endpoints, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);
        $byTraffic = array_slice($endpoints, 0, $top);

        $heavyCandidates = array_filter($endpoints, static fn (array $row): bool => ($row['count'] ?? 0) >= 1);
        usort($heavyCandidates, static function (array $a, array $b): int {
            $avg = ($b['avg_ms'] ?? 0) <=> ($a['avg_ms'] ?? 0);
            if ($avg !== 0) {
                return $avg;
            }

            return ($b['max_ms'] ?? 0) <=> ($a['max_ms'] ?? 0);
        });
        $heavy = array_slice(array_values($heavyCandidates), 0, $top);

        $apiEndpoints = array_values(array_filter($endpoints, static fn (array $row): bool => ($row['type'] ?? '') === 'api'));
        $httpEndpoints = array_values(array_filter($endpoints, static fn (array $row): bool => ($row['type'] ?? '') === 'http'));

        $totalRequests = count($entries);

        return [
            'enabled' => self::enabled(),
            'log_path' => self::logPath(),
            'started_at' => $startedAt !== null ? gmdate('c', (int) $startedAt) : null,
            'updated_at' => $updatedAt !== null ? gmdate('c', (int) $updatedAt) : null,
            'total_requests' => $totalRequests,
            'unique_endpoints' => count($endpoints),
            'avg_duration_ms' => round($totalMs / max(1, $totalRequests), 2),
            'api_requests' => count(array_filter($entries, static fn (array $e): bool => ($e['endpoint']['type'] ?? '') === 'api')),
            'http_requests' => count(array_filter($entries, static fn (array $e): bool => ($e['endpoint']['type'] ?? '') === 'http')),
            'heavy_endpoints' => $heavy,
            'high_traffic_endpoints' => $byTraffic,
            'api_by_traffic' => array_slice($apiEndpoints, 0, $top),
            'http_by_traffic' => array_slice($httpEndpoints, 0, $top),
        ];
    }

    public static function reset(): void
    {
        self::$buffer = [];
        $path = self::logPath();
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function logPath(): string
    {
        if (function_exists('alias')) {
            try {
                return alias(\DIRECTORIES::STORAGE_DIR->name)
                    . DIRECTORY_SEPARATOR . 'metrics'
                    . DIRECTORY_SEPARATOR . 'requests.jsonl';
            } catch (\Throwable) {
            }
        }

        $base = defined('BASE_PATH') ? (string) BASE_PATH : (string) getcwd();

        return $base . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'metrics' . DIRECTORY_SEPARATOR . 'requests.jsonl';
    }

    private static function shouldSkip(Request $request): bool
    {
        $path = $request->getPathInfo();

        return str_starts_with($path, '/__pionia/');
    }

    /**
     * @return array{type: string, service?: string, action?: string, label: string, path?: string, method?: string}
     */
    private static function resolveEndpoint(Request $request): array
    {
        if ($request->isMethod('GET')) {
            $service = $request->attributes->getString('service');
            $action = $request->attributes->getString('action');
            if ($service !== null && $action !== null) {
                return [
                    'type' => 'api',
                    'service' => $service,
                    'action' => $action,
                    'label' => $service . '::' . $action,
                ];
            }
        }

        $data = $request->getData();
        $service = $data->get('service');
        $action = $data->get('action');
        if (is_string($service) && $service !== '' && is_string($action) && $action !== '') {
            return [
                'type' => 'api',
                'service' => $service,
                'action' => $action,
                'label' => $service . '::' . $action,
            ];
        }

        $method = $request->getMethod();
        $path = $request->getPathInfo() ?: '/';

        return [
            'type' => 'http',
            'method' => $method,
            'path' => $path,
            'label' => $method . ' ' . $path,
        ];
    }

    /**
     * @param array<string, mixed> $endpoint
     */
    private static function bucketKey(array $endpoint): string
    {
        return (string) ($endpoint['label'] ?? json_encode($endpoint));
    }

    /**
     * @param array<string, mixed> $endpoint
     * @return array<string, mixed>
     */
    private static function freshBucket(array $endpoint): array
    {
        return [
            'type' => $endpoint['type'] ?? 'http',
            'label' => $endpoint['label'] ?? '',
            'service' => $endpoint['service'] ?? null,
            'action' => $endpoint['action'] ?? null,
            'method' => $endpoint['method'] ?? null,
            'path' => $endpoint['path'] ?? null,
            'count' => 0,
            'total_ms' => 0.0,
            'max_ms' => 0.0,
            'min_ms' => INF,
            'errors' => 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function emptySnapshot(): array
    {
        return [
            'enabled' => self::enabled(),
            'log_path' => self::logPath(),
            'started_at' => null,
            'updated_at' => null,
            'total_requests' => 0,
            'unique_endpoints' => 0,
            'avg_duration_ms' => 0.0,
            'api_requests' => 0,
            'http_requests' => 0,
            'heavy_endpoints' => [],
            'high_traffic_endpoints' => [],
            'api_by_traffic' => [],
            'http_by_traffic' => [],
        ];
    }

    /**
     * @param list<string> $lines
     */
    private static function appendLines(array $lines): void
    {
        $path = self::logPath();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return;
        }

        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            fseek($handle, 0, SEEK_END);
            fwrite($handle, implode('', $lines));
            fflush($handle);

            $size = ftell($handle);
            if ($size !== false && $size > 2_000_000) {
                self::trimLog($handle);
            }
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private static function appendLine(string $line): void
    {
        self::appendLines([$line]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function readEntries(): array
    {
        $path = self::logPath();
        if (!is_readable($path)) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        if (count($lines) > self::MAX_LINES) {
            $lines = array_slice($lines, -self::MAX_LINES);
        }

        $entries = [];
        foreach ($lines as $line) {
            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $entries[] = $decoded;
                }
            } catch (\Throwable) {
            }
        }

        return $entries;
    }

    /**
     * @param resource $handle
     */
    private static function trimLog($handle): void
    {
        rewind($handle);
        $content = stream_get_contents($handle);
        if (!is_string($content) || $content === '') {
            return;
        }

        $lines = explode("\n", trim($content));
        $lines = array_slice($lines, -self::MAX_LINES);
        $trimmed = implode("\n", $lines) . "\n";

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, $trimmed);
    }
}
