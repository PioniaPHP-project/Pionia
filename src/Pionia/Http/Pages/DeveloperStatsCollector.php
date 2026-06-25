<?php

namespace Pionia\Http\Pages;

use Pionia\Collections\Arrayable;
use Pionia\Documentation\MoonlightDocCollector;
use Pionia\Http\Request\Request;
use Pionia\Realm\AppRealm;
use Pionia\Realm\RealmContract;

class DeveloperStatsCollector
{
    public function __construct(
        private readonly RealmContract $app,
        private readonly Request $request,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        return [
            'generated_at' => gmdate('c'),
            'health' => $this->healthSummary(),
            'application' => $this->application(),
            'runtime' => $this->runtime(),
            'system' => $this->system(),
            'request' => $this->requestSnapshot(),
            'stack' => $this->stack(),
            'services' => $this->services(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function healthSummary(): array
    {
        $checks = [
            'application' => ['status' => 'ok', 'message' => 'Booted'],
            'database' => $this->probeDatabase(),
            'opcache' => $this->probeOpcache(),
            'memory' => $this->probeMemory(),
            'disk' => $this->probeDisk(),
        ];

        $statuses = array_column($checks, 'status');
        $overall = in_array('critical', $statuses, true) ? 'critical'
            : (in_array('warn', $statuses, true) ? 'warn' : 'ok');

        return ['overall' => $overall, 'checks' => $checks];
    }

    /**
     * @return array<string, mixed>
     */
    private function application(): array
    {
        $switches = $this->app->getSilently(AppRealm::SWITCHES_TAGS);
        $switchMap = $switches instanceof Arrayable ? $switches->all() : (array) ($switches ?? []);

        return [
            'name' => $this->app->getAppName(),
            'framework' => (string) $this->app->getOrDefault('FRAMEWORK', 'Pionia Framework'),
            'framework_version' => property_exists($this->app, 'appVersion') ? (string) $this->app->appVersion : '2.x',
            'environment' => (string) env('APP_ENV', 'development'),
            'debug' => $this->app->isDebug(),
            'api_base' => apiBase(),
            'api_versions' => array_keys($switchMap),
            'bootstrap_path' => container_path(),
            'container_path' => container_path(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runtime(): array
    {
        $data = [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'runtime_mode' => runtimeMode()->value,
            'timezone' => date_default_timezone_get(),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_input_time' => ini_get('max_input_time'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'display_errors' => ini_get('display_errors'),
            'error_reporting' => error_reporting(),
            'extensions_loaded' => count(get_loaded_extensions()),
            'zend_version' => zend_version(),
        ];

        if (function_exists('opcache_get_status')) {
            $status = @opcache_get_status(false);
            if (is_array($status)) {
                $data['opcache'] = [
                    'enabled' => $status['opcache_enabled'] ?? false,
                    'hit_rate' => isset($status['opcache_statistics']['opcache_hit_rate'])
                        ? round((float) $status['opcache_statistics']['opcache_hit_rate'], 2)
                        : null,
                    'cached_scripts' => $status['opcache_statistics']['num_cached_scripts'] ?? null,
                    'memory_used_mb' => isset($status['memory_usage']['used_memory'])
                        ? round($status['memory_usage']['used_memory'] / 1048576, 2)
                        : null,
                ];
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function system(): array
    {
        $root = $this->appRoot();
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : null;
        $usage = function_exists('getrusage') ? getrusage() : null;
        $volume = $this->volumeDiskMetrics($root);
        $storagePath = $this->storagePath();

        return [
            'hostname' => gethostname() ?: php_uname('n'),
            'os' => php_uname('s') . ' ' . php_uname('r'),
            'architecture' => php_uname('m'),
            'process_id' => getmypid(),
            'load_average' => $load,
            'memory' => [
                'usage_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
                'limit' => ini_get('memory_limit'),
                'usage_human' => $this->formatBytes(memory_get_usage(true)),
                'peak_human' => $this->formatBytes(memory_get_peak_usage(true)),
            ],
            'volume' => $volume,
            'project_storage' => [
                'path' => $storagePath,
                'size_bytes' => $this->directorySize($storagePath),
                'size_human' => $this->formatBytes($this->directorySize($storagePath)),
            ],
            'cpu_time' => is_array($usage) ? [
                'user_usec' => ($usage['ru_utime.tv_sec'] ?? 0) * 1_000_000 + ($usage['ru_utime.tv_usec'] ?? 0),
                'system_usec' => ($usage['ru_stime.tv_sec'] ?? 0) * 1_000_000 + ($usage['ru_stime.tv_usec'] ?? 0),
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function requestSnapshot(): array
    {
        return [
            'method' => $this->request->getMethod(),
            'path' => $this->request->getPathInfo(),
            'uri' => $this->request->getRequestUri(),
            'host' => $this->request->getHost(),
            'port' => $this->request->getPort(),
            'scheme' => $this->request->getScheme(),
            'ip' => $this->request->getClientIp(),
            'user_agent' => $this->request->headers->get('User-Agent'),
            'request_id' => $this->request->headers->get('X-Request-Id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function stack(): array
    {
        return [
            'routes' => count(allRoutes()->all()),
            'commands' => count((commands() instanceof Arrayable ? commands()->all() : (array) commands())),
            'middlewares' => count((middlewares() instanceof Arrayable ? middlewares()->all() : (array) middlewares())),
            'authentications' => count((authentications() instanceof Arrayable ? authentications()->all() : (array) authentications())),
            'docs_enabled' => apiDocsEnabled(),
            'stats_enabled' => apiStatsEnabled(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function services(): array
    {
        try {
            $catalog = (new MoonlightDocCollector())->collect();
            $summary = [];
            foreach ($catalog->versions as $version => $services) {
                foreach ($services as $alias => $service) {
                    $summary[] = [
                        'version' => $version,
                        'alias' => $alias,
                        'class' => $service->className,
                        'actions' => count($service->actions),
                    ];
                }
            }

            return [
                'title' => $catalog->title,
                'versions' => count($catalog->versions),
                'services' => $summary,
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function probeDatabase(): array
    {
        try {
            $connection = connectionManager()->connection('default');
            $pdo = $connection->getPdo();
            if (!$pdo instanceof \PDO) {
                return ['status' => 'critical', 'message' => 'No PDO connection (check database settings)'];
            }
            $pdo->query('SELECT 1');

            return ['status' => 'ok', 'message' => 'Connected (default)'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => 'Database unreachable: ' . $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, message: string}
     */
    private function probeOpcache(): array
    {
        if (!function_exists('opcache_get_status')) {
            return ['status' => 'warn', 'message' => 'OPcache extension not loaded'];
        }

        $status = @opcache_get_status(false);
        if (!is_array($status) || empty($status['opcache_enabled'])) {
            return ['status' => 'warn', 'message' => 'OPcache disabled'];
        }

        return ['status' => 'ok', 'message' => 'OPcache enabled'];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function probeMemory(): array
    {
        $limit = ini_get('memory_limit');
        $bytes = $this->parseIniSize((string) $limit);
        if ($bytes <= 0) {
            return ['status' => 'ok', 'message' => 'Memory: ' . $this->formatBytes(memory_get_usage(true))];
        }

        $used = memory_get_usage(true);
        $percent = round(($used / $bytes) * 100, 1);

        if ($percent >= 90) {
            return ['status' => 'critical', 'message' => "Memory at {$percent}% of {$limit}"];
        }

        if ($percent >= 75) {
            return ['status' => 'warn', 'message' => "Memory at {$percent}% of {$limit}"];
        }

        return ['status' => 'ok', 'message' => "Memory at {$percent}% of {$limit}"];
    }

    /**
     * @return array{status: string, message: string}
     */
    private function probeDisk(): array
    {
        $volume = $this->volumeDiskMetrics($this->appRoot());
        $usedPercent = $volume['used_percent'];

        if ($usedPercent === null) {
            return ['status' => 'warn', 'message' => 'Volume disk metrics unavailable'];
        }

        $path = $volume['path'];
        if ($usedPercent >= 95) {
            return ['status' => 'critical', 'message' => "Volume {$usedPercent}% full at {$path}"];
        }

        if ($usedPercent >= 85) {
            return ['status' => 'warn', 'message' => "Volume {$usedPercent}% full at {$path}"];
        }

        return ['status' => 'ok', 'message' => "Volume {$usedPercent}% full ({$volume['free_human']} free on disk containing app)"];
    }

    private function appRoot(): string
    {
        if (defined('BASE_PATH')) {
            return (string) BASE_PATH;
        }

        $path = container_path();

        return is_file($path) ? dirname(dirname($path)) : $path;
    }

    private function storagePath(): string
    {
        try {
            return directoryFor(\DIRECTORIES::STORAGE_DIR->name) ?? ($this->appRoot() . DIRECTORY_SEPARATOR . 'storage');
        } catch (\Throwable) {
            return $this->appRoot() . DIRECTORY_SEPARATOR . 'storage';
        }
    }

    /**
     * @return array{scope: string, path: string, total_bytes: int, free_bytes: int, used_bytes: int, used_percent: float|null, total_human: string, free_human: string}
     */
    private function volumeDiskMetrics(string $root): array
    {
        $resolved = realpath($root) ?: $root;
        $diskTotal = @disk_total_space($resolved) ?: 0;
        $diskFree = @disk_free_space($resolved) ?: 0;

        return [
            'scope' => 'filesystem_volume',
            'path' => $resolved,
            'total_bytes' => (int) $diskTotal,
            'free_bytes' => (int) $diskFree,
            'used_bytes' => max(0, (int) $diskTotal - (int) $diskFree),
            'used_percent' => $diskTotal > 0 ? round((1 - $diskFree / $diskTotal) * 100, 1) : null,
            'total_human' => $this->formatBytes((int) $diskTotal),
            'free_human' => $this->formatBytes((int) $diskFree),
        ];
    }

    private function directorySize(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) max($bytes, 0);
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            ++$i;
        }

        return round($value, 2) . ' ' . $units[$i];
    }

    private function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => (int) $number,
        };
    }
}
