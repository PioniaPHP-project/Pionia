<?php

namespace Pionia\Http\Pages;

use Pionia\Collections\Arrayable;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Realm\RealmContract;
use Symfony\Component\Routing\Route;

class FrameworkWelcomePage
{
    public const ASSET_PREFIX = '/__pionia';

    public function __construct(
        private readonly RealmContract $app,
        private readonly Request $request,
    ) {
    }

    public static function for(Request $request, RealmContract $app): self
    {
        return new self($app, $request);
    }

    public static function resourcesPath(): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'public';
    }

    public function toResponse(): Response
    {
        return new Response($this->render(), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function render(): string
    {
        $appName = $this->e($this->app->getAppName());
        $framework = $this->e((string) $this->app->getOrDefault('FRAMEWORK', 'Pionia Framework'));
        $frameworkTag = $this->e((string) $this->app->getOrDefault('FRAMEWORK_DESCRIPTION', 'PHP REST Framework'));
        $apiVersionBase = $this->e(apiVersionPath());
        $pingPath = $this->e(apiPingPath());
        $envName = $this->e((string) env('APP_ENV', 'development'));
        $phpVersion = $this->e(PHP_VERSION);
        $frameworkVersion = $this->e(
            property_exists($this->app, 'appVersion') ? (string) $this->app->appVersion : '2.x'
        );
        $year = date('Y');
        $logo = self::ASSET_PREFIX . '/pionia_logo.webp';
        $favicon = self::ASSET_PREFIX . '/favicon.ico';
        $welcomeCss = self::ASSET_PREFIX . '/welcome.css';
        $debug = realm()->isDebug();
        $port = (int) $this->request->getPort();
        $host = $this->e($this->request->getHost());
        $statusLine = $debug && !$this->welcomeFlag('HIDE_PORT')
            ? "{$appName} is running on {$host}:{$port}{$apiVersionBase}"
            : "{$appName} is ready to serve requests.";

        $quickStart = !$this->welcomeFlag('HIDE_QUICK_START') ? $this->quickStartBlock($apiVersionBase, $pingPath, $port) : '';
        $debugPanel = $this->shouldShowContextPanel() ? $this->debugPanel() : '';
        $features = $this->featuresSection($framework);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$appName} · {$framework}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{$frameworkTag}">
    <link rel="icon" href="{$favicon}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{$welcomeCss}">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark pionia-nav py-3">
    <div class="container">
        <div class="d-flex align-items-center gap-3">
            <img src="{$favicon}" alt="" width="36" height="36">
            <div>
                <div class="navbar-brand mb-0 p-0">{$framework}</div>
                <div class="small text-white-50">{$appName}</div>
            </div>
        </div>
        <div class="d-none d-md-flex gap-2 ms-auto">
            <a href="https://pionia.netlify.app/" target="_blank" rel="noopener" class="btn btn-sm btn-pionia-outline">Docs</a>
            <a href="https://github.com/PioniaPHP-project/Application" target="_blank" rel="noopener" class="btn btn-sm btn-pionia-outline">GitHub</a>
        </div>
    </div>
</nav>

<section class="hero">
    <div class="container hero-inner">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <img src="{$favicon}" alt="" class="hero-logo mb-4">
                <h1>{$appName}</h1>
                <p class="hero-lead status-line mb-4">{$statusLine}</p>
                <p class="hero-lead mb-4">{$frameworkTag}</p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{$pingPath}" class="btn btn-lg btn-pionia">Check API status</a>
                    <a href="https://pionia.netlify.app/" target="_blank" rel="noopener" class="btn btn-lg btn-pionia-outline">Read the docs</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="stat-grid">
                    <div class="stat-card"><span class="label">Environment</span><span class="value">{$envName}</span></div>
                    <div class="stat-card"><span class="label">PHP</span><span class="value">{$phpVersion}</span></div>
                    <div class="stat-card"><span class="label">Framework</span><span class="value">{$framework} v{$frameworkVersion}</span></div>
                    <div class="stat-card"><span class="label">API version</span><span class="value">{$apiVersionBase}</span></div>
                </div>
            </div>
        </div>
    </div>
</section>

{$features}
{$quickStart}
{$debugPanel}

<footer class="pionia-footer text-center py-5">
    <img src="{$logo}" class="d-block mx-auto mb-3" alt="{$appName}">
    <div class="small">© {$year} {$framework} · PHP {$phpVersion} · {$envName}</div>
    <div class="small text-white-50 mt-2">Deploy <code class="text-white-50">public/index.html</code> to replace this page with your frontend.</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
HTML;
    }

    private function featuresSection(string $framework): string
    {
        return <<<HTML
<section class="py-5 section-muted">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="section-title">Built for API developers with deadlines</h2>
            <p class="text-muted mb-0">Switches, services, middleware, and environment config — without ceremony.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="feature-card">
                    <span class="feature-icon mb-3"><i class="bi bi-lightning-charge-fill"></i></span>
                    <h5 class="fw-semibold">Fast by default</h5>
                    <p class="text-muted mb-0">Lean request lifecycle, switch-based routing, and JSON-first responses.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <span class="feature-icon mb-3"><i class="bi bi-diagram-3-fill"></i></span>
                    <h5 class="fw-semibold">Modular architecture</h5>
                    <p class="text-muted mb-0">Register services per switch version, chain middleware and auth providers.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-card">
                    <span class="feature-icon mb-3"><i class="bi bi-sliders"></i></span>
                    <h5 class="fw-semibold">Environment-driven</h5>
                    <p class="text-muted mb-0">Configure databases, logging, and CORS from <code>environment/</code>.</p>
                </div>
            </div>
        </div>
    </div>
</section>
HTML;
    }

    private function quickStartBlock(string $apiVersionBase, string $pingPath, int $port): string
    {
        $pingUrl = $this->e("http://127.0.0.1:{$port}{$pingPath}");
        $postUrl = $this->e("http://127.0.0.1:{$port}{$apiVersionBase}");

        return <<<HTML
<section class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="quickstart-card">
                    <div class="card-header">Quick start</div>
                    <pre class="code-block"># Ping the API
curl -s "{$pingUrl}" | jq

# Call a service action
curl -s -X POST "{$postUrl}" \\
  -H "Content-Type: application/json" \\
  -d '{"service":"auth","action":"list_auth"}' | jq</pre>
                </div>
            </div>
        </div>
    </div>
</section>
HTML;
    }

    private function debugPanel(): string
    {
        return <<<HTML
<section class="py-5 section-muted" id="debug-context">
    <div class="container">
        <div class="text-center mb-4">
            <h2 class="section-title">Developer context</h2>
            <p class="text-muted mb-0">Live snapshot of your bootstrapped application. Visible only in debug mode.</p>
        </div>
        <div class="debug-shell">
            <ul class="nav nav-tabs" id="pioniaContextTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="env-tab" data-bs-toggle="tab" data-bs-target="#env-panel" type="button" role="tab">Environment</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="routes-tab" data-bs-toggle="tab" data-bs-target="#routes-panel" type="button" role="tab">Routes</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="commands-tab" data-bs-toggle="tab" data-bs-target="#commands-panel" type="button" role="tab">Commands</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="middlewares-tab" data-bs-toggle="tab" data-bs-target="#middlewares-panel" type="button" role="tab">Middlewares</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="auth-tab" data-bs-toggle="tab" data-bs-target="#auth-panel" type="button" role="tab">Authentications</button>
                </li>
            </ul>
            <div class="tab-content p-3 p-md-4" id="pioniaContextTabsContent">
                <div class="tab-pane fade show active" id="env-panel" role="tabpanel">
                    {$this->table('Variable', 'Value', $this->environmentRows())}
                </div>
                <div class="tab-pane fade" id="routes-panel" role="tabpanel">
                    {$this->table('Name', 'Path · Methods · Handler', $this->routeRows())}
                </div>
                <div class="tab-pane fade" id="commands-panel" role="tabpanel">
                    {$this->table('Name', 'Class', $this->keyValueRows(commands()))}
                </div>
                <div class="tab-pane fade" id="middlewares-panel" role="tabpanel">
                    {$this->table('Name', 'Class', $this->keyValueRows(middlewares()))}
                </div>
                <div class="tab-pane fade" id="auth-panel" role="tabpanel">
                    {$this->table('Name', 'Class', $this->keyValueRows(authentications()))}
                </div>
            </div>
        </div>
    </div>
</section>
HTML;
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
<div class="table-responsive">
    <table class="table table-hover debug-table align-middle">
        <thead><tr><th>{$this->e($headingA)}</th><th>{$this->e($headingB)}</th></tr></thead>
        <tbody>{$body}</tbody>
    </table>
</div>
HTML;
    }

  /**
   * @return list<array{0: string, 1: string}>
   */
    private function environmentRows(): array
    {
        if ($this->welcomeFlag('HIDE_ENV')) {
            return [];
        }

        $rows = [];
        foreach ($this->envKeys() as $key) {
            if ($key === '') {
                continue;
            }

            $value = env($key);
            $display = $this->isSensitiveEnvKey($key)
                ? '<span class="text-muted">••••••••</span>'
                : $this->formatCellValue($value);

            $rows[] = ['<code>' . $this->e($key) . '</code>', $display];
        }

        return $rows;
    }

  /**
   * @return list<array{0: string, 1: string}>
   */
    private function routeRows(): array
    {
        $rows = [];

        foreach (allRoutes()->all() as $name => $route) {
            if (!$route instanceof Route) {
                continue;
            }

            $controller = $route->getDefaults()['_controller'] ?? '—';
            $methods = implode(', ', $route->getMethods());
            $detail = '<code>' . $this->e($route->getPath()) . '</code>'
                . '<div class="small text-muted mt-1">' . $this->e($methods) . ' · ' . $this->e((string) $controller) . '</div>';

            $rows[] = [$this->e((string) $name), $detail];
        }

        return $rows;
    }

  /**
   * @return list<array{0: string, 1: string}>
   */
    private function keyValueRows(mixed $collection): array
    {
        $rows = [];
        $items = $collection instanceof Arrayable ? $collection->all() : (array) $collection;

        foreach ($items as $key => $value) {
            $rows[] = [$this->e((string) $key), '<code>' . $this->e($this->stringifyValue($value)) . '</code>'];
        }

        return $rows;
    }

  /** @return list<string> */
    private function envKeys(): array
    {
        $keys = envKeys();
        if ($keys !== []) {
            return array_values(array_filter(array_map('trim', $keys)));
        }

        $merged = array_merge(array_keys($_ENV), array_keys($_SERVER));

        return array_values(array_unique(array_filter($merged, static fn (string $key): bool => !str_starts_with($key, 'SYMFONY_'))));
    }

    private function shouldShowContextPanel(): bool
    {
        if (!realm()->isDebug()) {
            return false;
        }

        return !$this->welcomeFlag('HIDE_CONTEXT');
    }

    private function welcomeFlag(string $key): bool
    {
        $welcome = env('welcome', []);
        if (!is_array($welcome) || !array_key_exists($key, $welcome)) {
            return false;
        }

        return filter_var($welcome[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private function isSensitiveEnvKey(string $key): bool
    {
        $hidden = ['password', 'pass', 'pin', 'token', 'secret', 'pwd', 'credential', 'cvv'];
        $logging = env('logging', []);
        if (is_array($logging) && !empty($logging['HIDE_IN_LOGS'])) {
            $hidden = array_merge($hidden, array_map('trim', explode(',', (string) $logging['HIDE_IN_LOGS'])));
        }

        $lower = strtolower($key);
        foreach ($hidden as $needle) {
            if ($needle !== '' && str_contains($lower, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    private function formatCellValue(mixed $value): string
    {
        if (is_array($value) || $value instanceof Arrayable) {
            $encoded = json_encode($value instanceof Arrayable ? $value->all() : $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            return '<pre class="mb-0 small">' . $this->e((string) $encoded) . '</pre>';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '<span class="text-muted">null</span>';
        }

        return $this->e((string) $value);
    }

    private function stringifyValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_scalar($value) || $value === null) {
            return (string) json_encode($value);
        }

        if ($value instanceof Arrayable) {
            return json_encode($value->all()) ?: '';
        }

        return is_object($value) ? $value::class : gettype($value);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
