<?php

namespace Pionia\Documentation;

use Pionia\Documentation\Contracts\MoonlightApiCatalog;

/**
 * Renders a Swagger-like interactive API reference (Scalar) for Moonlight OpenAPI specs.
 *
 * Scalar manages its own light/dark toggle — no Pionia theme scripts on this page.
 * OpenAPI path keys use `#service.action` fragments for navigation. Try-it-out uses
 * Scalar `onBeforeRequest` (when available) plus a fetch fallback to POST the real
 * Moonlight dispatch URL from `x-pionia-dispatch.url`.
 */
class ApiDocsUiExporter
{
    public function render(MoonlightApiCatalog $catalog, string $specUrl = '/docs/openapi.json'): string
    {
        $title = htmlspecialchars($catalog->title . ' API', ENT_QUOTES, 'UTF-8');
        $specUrl = htmlspecialchars($specUrl, ENT_QUOTES, 'UTF-8');
        $favicon = '/__pionia/favicon.ico';

        // Static JSON config (functions cannot live in data-configuration JSON)
        $scalarConfig = htmlspecialchars(
            json_encode([
                'theme' => 'purple',
                'layout' => 'modern',
                'hideDownloadButton' => false,
                'darkMode' => true,
                'hideModels' => true,
                'tagsSorter' => 'alpha',
                'operationsSorter' => 'alpha',
                'defaultOpenAllTags' => false,
            ], JSON_THROW_ON_ERROR),
            ENT_QUOTES,
            'UTF-8',
        );

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        body { margin: 0; font-family: Inter, system-ui, -apple-system, sans-serif; }
        .pionia-docs-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.65rem 1rem;
            background: #3d104f;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .pionia-docs-toolbar-title { font-weight: 600; font-size: 0.95rem; }
        .pionia-docs-toolbar a {
            color: #fff;
            text-decoration: none;
            font-size: 0.875rem;
            padding: 0.35rem 0.75rem;
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
        }
        .pionia-docs-toolbar a:hover { background: rgba(255, 255, 255, 0.08); }
    </style>
</head>
<body>
<div class="pionia-docs-toolbar">
    <div style="display:flex;align-items:center;gap:0.5rem;">
        <img src="{$favicon}" alt="" width="24" height="24">
        <span class="pionia-docs-toolbar-title">{$title}</span>
    </div>
    <a href="/">Home</a>
</div>
<script>
(function () {
    function rewriteMoonlightUrl(url) {
        if (typeof url !== 'string') {
            return url;
        }
        // Strip documentation fragments: /api/v1#service.action → /api/v1
        if (url.indexOf('#') !== -1) {
            url = url.split('#')[0];
        }
        // Legacy fake paths: /api/v1/moonlight/svc/action → /api/v1/
        var m = url.match(/^(https?:\\/\\/[^\\/]+)?(\\/api\\/[^\\/]+)(\\/moonlight\\/[^?]*)(.*)$/i);
        if (m) {
            return (m[1] || '') + m[2] + '/' + (m[4] || '');
        }
        return url;
    }

    // Prefer Scalar onBeforeRequest when the CDN supports requestBuilder
    window.__pioniaScalarOnBeforeRequest = function (ctx) {
        var builder = (ctx && (ctx.requestBuilder || ctx.request)) || null;
        if (!builder) {
            return;
        }
        if (builder.path && typeof builder.path.raw === 'string') {
            builder.path.raw = rewriteMoonlightUrl(builder.path.raw);
        }
        if (typeof builder.url === 'string') {
            builder.url = rewriteMoonlightUrl(builder.url);
        }
        // If operation metadata is exposed, prefer x-pionia-dispatch.url
        var op = ctx.operation || ctx.operationObject || null;
        var dispatch = op && (op['x-pionia-dispatch'] || (op.extensions && op.extensions['x-pionia-dispatch']));
        if (dispatch && dispatch.url && builder.path) {
            builder.path.raw = String(dispatch.url);
        }
    };

    // Fetch fallback (covers CDN builds that ignore onBeforeRequest in data-configuration)
    var origFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        if (typeof input === 'string') {
            input = rewriteMoonlightUrl(input);
        } else if (input instanceof Request) {
            var next = rewriteMoonlightUrl(input.url);
            if (next !== input.url) {
                input = new Request(next, input);
            }
        }
        return origFetch(input, init);
    };

    // Apply configuration after Scalar loads (functions cannot be JSON-serialized)
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('api-reference');
        if (!el) {
            return;
        }
        try {
            var cfg = JSON.parse(el.getAttribute('data-configuration') || '{}');
            cfg.onBeforeRequest = window.__pioniaScalarOnBeforeRequest;
            el.dataset.configuration = JSON.stringify(cfg, function (k, v) {
                return typeof v === 'function' ? undefined : v;
            });
            // Keep callable on window for Scalar integrations that read global config
            window.Scalar = window.Scalar || {};
            window.Scalar.configuration = Object.assign({}, cfg, {
                onBeforeRequest: window.__pioniaScalarOnBeforeRequest
            });
        } catch (e) {}
    });
})();
</script>
<script
    id="api-reference"
    data-url="{$specUrl}"
    data-configuration='{$scalarConfig}'></script>
<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
<script>
(function () {
    // After Scalar boots, attach onBeforeRequest if the client exposes a config API
    var tries = 0;
    var timer = setInterval(function () {
        tries += 1;
        if (window.Scalar && window.Scalar.updateConfiguration) {
            window.Scalar.updateConfiguration({
                onBeforeRequest: window.__pioniaScalarOnBeforeRequest
            });
            clearInterval(timer);
        } else if (tries > 40) {
            clearInterval(timer);
        }
    }, 100);
})();
</script>
<noscript>
    <p style="font-family: system-ui, sans-serif; padding: 2rem;">
        API docs require JavaScript. OpenAPI spec: <a href="{$specUrl}">{$specUrl}</a>
    </p>
</noscript>
</body>
</html>
HTML;
    }
}
