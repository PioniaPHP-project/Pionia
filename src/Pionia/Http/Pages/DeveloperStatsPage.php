<?php

namespace Pionia\Http\Pages;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Realm\RealmContract;

class DeveloperStatsPage
{
    public function __construct(
        private readonly RealmContract $app,
        private readonly Request $request,
    ) {
    }

    public static function for(Request $request, RealmContract $app): self
    {
        return new self($app, $request);
    }

    public function toResponse(): Response
    {
        return new Response($this->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return (new DeveloperStatsCollector($this->app, $this->request))->collect();
    }

    private function render(): string
    {
        $stats = $this->payload();
        $context = new DeveloperContextData();
        $appName = $this->e($this->app->getAppName());
        $generated = $this->e((string) ($stats['generated_at'] ?? ''));
        $favicon = FrameworkWelcomePage::ASSET_PREFIX . '/favicon.ico';
        $welcomeCss = FrameworkWelcomePage::ASSET_PREFIX . '/welcome.css';
        $statsCss = FrameworkWelcomePage::ASSET_PREFIX . '/stats.css';
        $healthCards = $this->healthCards($stats['health']['checks'] ?? []);
        $overall = $this->e((string) ($stats['health']['overall'] ?? 'ok'));
        $overviewRows = $this->overviewRows($stats);
        $systemRows = $this->systemRows($stats['system'] ?? []);
        $runtimeRows = $this->flatRows($stats['runtime'] ?? []);
        $themeInit = PioniaTheme::initScript();
        $themeAssets = PioniaTheme::stylesheetTags();
        $themeToggle = PioniaTheme::toggleButton();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$appName} · Developer stats</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <link rel="icon" href="{$favicon}">
    {$themeInit}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{$welcomeCss}">
    <link rel="stylesheet" href="{$statsCss}">
    {$themeAssets}
</head>
<body class="stats-page">
<nav class="navbar navbar-dark pionia-nav py-3">
    <div class="container-fluid px-4">
        <div class="d-flex align-items-center gap-3">
            <img src="{$favicon}" alt="" width="32" height="32">
            <div>
                <div class="navbar-brand mb-0 p-0">Developer stats</div>
                <div class="small text-white-50">{$appName}</div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-3 ms-auto">
            {$themeToggle}
            <span class="badge health-overall health-{$overall}">Overall: {$overall}</span>
            <span class="small text-white-50">Updated {$generated}</span>
            <a href="/stats.json{$this->tokenQuery()}" class="btn btn-sm btn-pionia-outline">JSON</a>
            <a href="/" class="btn btn-sm btn-pionia-outline">Home</a>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4">
    <section class="health-grid mb-4">{$healthCards}</section>

    <div class="debug-shell">
        <ul class="nav nav-tabs" id="statsTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#overview-panel" type="button">Overview</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#system-panel" type="button">System</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#runtime-panel" type="button">Runtime</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#env-panel" type="button">Environment</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#routes-panel" type="button">Routes</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#stack-panel" type="button">Stack</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#services-panel" type="button">Services</button></li>
        </ul>
        <div class="tab-content p-3 p-md-4">
            <div class="tab-pane fade show active" id="overview-panel">{$this->table('Metric', 'Value', $overviewRows)}</div>
            <div class="tab-pane fade" id="system-panel">{$this->table('Metric', 'Value', $systemRows)}</div>
            <div class="tab-pane fade" id="runtime-panel">{$this->table('Setting', 'Value', $runtimeRows)}</div>
            <div class="tab-pane fade" id="env-panel">{$this->table('Variable', 'Value', $context->environmentRows())}</div>
            <div class="tab-pane fade" id="routes-panel">{$this->table('Name', 'Path · Methods · Handler', $context->routeRows())}</div>
            <div class="tab-pane fade" id="stack-panel">
                {$this->table('Commands', 'Class', $context->keyValueRows(commands()))}
                {$this->table('Middlewares', 'Class', $context->keyValueRows(middlewares()))}
                {$this->table('Authentications', 'Class', $context->keyValueRows(authentications()))}
            </div>
            <div class="tab-pane fade" id="services-panel">{$this->table('Version / Service', 'Class · Actions', $context->serviceRows())}</div>
        </div>
    </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
    }

    /**
     * @param array<string, array{status: string, message: string}> $checks
     */
    private function healthCards(array $checks): string
    {
        $html = '';
        foreach ($checks as $name => $check) {
            $status = $this->e((string) ($check['status'] ?? 'ok'));
            $message = $this->e((string) ($check['message'] ?? ''));
            $label = $this->e(ucfirst(str_replace('_', ' ', (string) $name)));
            $html .= <<<HTML
<div class="health-card health-{$status}">
    <div class="health-label">{$label}</div>
    <div class="health-status"><i class="bi {$this->healthIcon($check['status'] ?? 'ok')}"></i> {$status}</div>
    <div class="health-message">{$message}</div>
</div>
HTML;
        }

        return $html;
    }

    private function healthIcon(string $status): string
    {
        return match ($status) {
            'critical' => 'bi-x-circle-fill',
            'warn' => 'bi-exclamation-triangle-fill',
            default => 'bi-check-circle-fill',
        };
    }

    /**
     * @param array<string, mixed> $stats
     * @return list<array{0: string, 1: string}>
     */
    private function overviewRows(array $stats): array
    {
        $app = $stats['application'] ?? [];
        $req = $stats['request'] ?? [];
        $stack = $stats['stack'] ?? [];
        $services = $stats['services'] ?? [];

        return [
            ['Application', $this->e((string) ($app['name'] ?? ''))],
            ['Environment', $this->e((string) ($app['environment'] ?? ''))],
            ['Debug mode', ($app['debug'] ?? false) ? 'true' : 'false'],
            ['Runtime mode', $this->e((string) ($stats['runtime']['runtime_mode'] ?? ''))],
            ['PHP', $this->e((string) ($stats['runtime']['php_version'] ?? ''))],
            ['API versions', $this->e(implode(', ', (array) ($app['api_versions'] ?? [])))],
            ['Routes registered', $this->e((string) ($stack['routes'] ?? 0))],
            ['Moonlight services', $this->e((string) count($services['services'] ?? []))],
            ['Request', '<code>' . $this->e(($req['method'] ?? '') . ' ' . ($req['path'] ?? '')) . '</code>'],
            ['Client IP', $this->e((string) ($req['ip'] ?? ''))],
            ['Docs exposed', ($stack['docs_enabled'] ?? false) ? 'yes' : 'no'],
            ['Stats exposed', ($stack['stats_enabled'] ?? false) ? 'yes' : 'no'],
        ];
    }

    /**
     * @param array<string, mixed> $system
     * @return list<array{0: string, 1: string}>
     */
    private function systemRows(array $system): array
    {
        $memory = $system['memory'] ?? [];
        $volume = $system['volume'] ?? [];
        $storage = $system['project_storage'] ?? [];
        $load = $system['load_average'] ?? null;
        $loadText = is_array($load) ? implode(' · ', array_map(static fn ($v) => (string) round((float) $v, 2), $load)) : 'n/a';

        return [
            ['Hostname', $this->e((string) ($system['hostname'] ?? ''))],
            ['OS', $this->e((string) ($system['os'] ?? ''))],
            ['Architecture', $this->e((string) ($system['architecture'] ?? ''))],
            ['Process ID', $this->e((string) ($system['process_id'] ?? ''))],
            ['Load average (1/5/15m)', $this->e($loadText)],
            ['Memory used', $this->e((string) ($memory['usage_human'] ?? ''))],
            ['Memory peak', $this->e((string) ($memory['peak_human'] ?? ''))],
            ['Memory limit', $this->e((string) ($memory['limit'] ?? ''))],
            ['Disk scope', $this->e('Filesystem volume containing the app (not project folder size)')],
            ['Volume path', '<code>' . $this->e((string) ($volume['path'] ?? '')) . '</code>'],
            ['Volume total', $this->e((string) ($volume['total_human'] ?? ''))],
            ['Volume free', $this->e((string) ($volume['free_human'] ?? ''))],
            ['Volume used %', $this->e((string) ($volume['used_percent'] ?? '')) . '%'],
            ['App storage path', '<code>' . $this->e((string) ($storage['path'] ?? '')) . '</code>'],
            ['App storage size', $this->e((string) ($storage['size_human'] ?? ''))],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<array{0: string, 1: string}>
     */
    private function flatRows(array $data): array
    {
        $rows = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES) ?: '';
            }
            $rows[] = [$this->e((string) $key), '<code>' . $this->e((string) $value) . '</code>'];
        }

        return $rows;
    }

    /**
     * @param list<array{0: string, 1: string}> $rows
     */
    private function table(string $headingA, string $headingB, array $rows): string
    {
        $body = '';
        foreach ($rows as [$left, $right]) {
            $body .= '<tr><td>' . $left . '</td><td>' . $right . '</td></tr>';
        }

        if ($body === '') {
            $body = '<tr><td colspan="2" class="text-muted">Nothing registered yet.</td></tr>';
        }

        return <<<HTML
<div class="table-responsive mb-4">
    <table class="table table-hover debug-table align-middle">
        <thead><tr><th>{$this->e($headingA)}</th><th>{$this->e($headingB)}</th></tr></thead>
        <tbody>{$body}</tbody>
    </table>
</div>
HTML;
    }

    private function tokenQuery(): string
    {
        $token = $this->request->query->get('token');

        return is_string($token) && $token !== '' ? ('?token=' . rawurlencode($token)) : '';
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
