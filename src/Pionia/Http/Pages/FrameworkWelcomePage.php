<?php

namespace Pionia\Http\Pages;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Realm\RealmContract;

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
        $welcomeCss = self::ASSET_PREFIX . '/welcome.css';
        $debug = realm()->isDebug();
        $port = (int) $this->request->getPort();
        $host = $this->e($this->request->getHost());
        $statusLine = $debug && !$this->welcomeFlag('HIDE_PORT')
            ? "{$appName} is running on {$host}:{$port}{$apiVersionBase}"
            : "{$appName} is ready to serve requests.";

        $quickStart = !$this->welcomeFlag('HIDE_QUICK_START') ? $this->quickStartBlock($apiVersionBase, $pingPath, $port) : '';
        $features = $this->featuresSection($framework);
        $devNavLinks = $this->developerNavLinks();
        $devHeroLinks = $this->developerHeroLinks();
        $devToolsStrip = $this->developerToolsStrip();
        $themeInit = PioniaTheme::initScript();
        $themeAssets = PioniaTheme::stylesheetTags();
        $themeToggle = PioniaTheme::toggleButton();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{$appName} · {$framework}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{$frameworkTag}">
    <link rel="icon" href="{$favicon}">
    {$themeInit}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="{$welcomeCss}">
    {$themeAssets}
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
        <div class="d-none d-md-flex gap-2 ms-auto align-items-center">
            {$themeToggle}
            {$devNavLinks}
            <a href="https://pionia.netlify.app/" target="_blank" rel="noopener" class="btn btn-sm btn-pionia-outline">Guide</a>
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
                    {$devHeroLinks}
                    <a href="https://pionia.netlify.app/" target="_blank" rel="noopener" class="btn btn-lg btn-pionia-outline">Framework guide</a>
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

{$devToolsStrip}
{$features}
{$quickStart}

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

    /**
     * @return list<array{href: string, label: string, description: string, icon: string}>
     */
    private function developerLinks(): array
    {
        $links = [];

        if (apiDocsEnabled()) {
            $links[] = [
                'href' => '/docs',
                'label' => 'API docs',
                'description' => 'Interactive Scalar reference for Moonlight services.',
                'icon' => 'bi-journal-code',
            ];
            $links[] = [
                'href' => '/docs/openapi.json',
                'label' => 'OpenAPI spec',
                'description' => 'Machine-readable OpenAPI 3.1 document.',
                'icon' => 'bi-braces-asterisk',
            ];
        }

        if (apiStatsEnabled()) {
            $links[] = [
                'href' => '/stats',
                'label' => 'Developer stats',
                'description' => 'Health, routes, environment, and runtime dashboard.',
                'icon' => 'bi-speedometer2',
            ];
        }

        return $links;
    }

    private function developerNavLinks(): string
    {
        $html = '';
        foreach ($this->developerLinks() as $link) {
            if ($link['href'] === '/docs/openapi.json') {
                continue;
            }

            $href = $this->e($link['href']);
            $label = $this->e($link['label']);
            $icon = $this->e($link['icon']);
            $html .= <<<HTML
<a href="{$href}" class="btn btn-sm btn-pionia-outline"><i class="bi {$icon} me-1" aria-hidden="true"></i>{$label}</a>
HTML;
        }

        return $html;
    }

    private function developerHeroLinks(): string
    {
        $html = '';
        foreach ($this->developerLinks() as $link) {
            if ($link['href'] === '/docs/openapi.json') {
                continue;
            }

            $href = $this->e($link['href']);
            $label = $this->e($link['label']);
            $html .= <<<HTML
<a href="{$href}" class="btn btn-lg btn-pionia-outline">{$label}</a>
HTML;
        }

        return $html;
    }

    private function developerToolsStrip(): string
    {
        $links = $this->developerLinks();
        if ($links === []) {
            return '';
        }

        $cards = '';
        foreach ($links as $link) {
            $href = $this->e($link['href']);
            $label = $this->e($link['label']);
            $description = $this->e($link['description']);
            $icon = $this->e($link['icon']);
            $cards .= <<<HTML
<div class="col-md-4">
    <a href="{$href}" class="developer-link-card">
        <span class="developer-link-icon"><i class="bi {$icon}" aria-hidden="true"></i></span>
        <span class="developer-link-label">{$label}</span>
        <span class="developer-link-desc">{$description}</span>
    </a>
</div>
HTML;
        }

        return <<<HTML
<section class="py-4 developer-tools-strip">
    <div class="container">
        <div class="row g-3">
            {$cards}
        </div>
    </div>
</section>
HTML;
    }

    private function quickStartBlock(string $apiVersionBase, string $pingPath, int $port): string
    {
        $pingUrl = $this->e("http://127.0.0.1:{$port}{$pingPath}");
        $postUrl = $this->e("http://127.0.0.1:{$port}{$apiVersionBase}");
        $catalogUrl = $this->e("http://127.0.0.1:{$port}/docs");
        $catalogLine = apiDocsEnabled()
            ? "\n\n# Browse interactive API docs\nopen {$catalogUrl}"
            : '';

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
  -d '{"service":"auth","action":"list_auth"}' | jq{$catalogLine}</pre>
                </div>
            </div>
        </div>
    </div>
</section>
HTML;
    }

    private function welcomeFlag(string $key): bool
    {
        $welcome = env('welcome', []);
        if (!is_array($welcome) || !array_key_exists($key, $welcome)) {
            return false;
        }

        return filter_var($welcome[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
