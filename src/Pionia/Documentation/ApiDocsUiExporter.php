<?php

namespace Pionia\Documentation;

use Pionia\Documentation\Contracts\MoonlightApiCatalog;

/**
 * Renders a Swagger-like interactive API reference (Scalar) for Moonlight OpenAPI specs.
 *
 * Scalar manages its own light/dark toggle — no Pionia theme scripts on this page.
 */
class ApiDocsUiExporter
{
    public function render(MoonlightApiCatalog $catalog, string $specUrl = '/docs/openapi.json'): string
    {
        $title = htmlspecialchars($catalog->title . ' API', ENT_QUOTES, 'UTF-8');
        $specUrl = htmlspecialchars($specUrl, ENT_QUOTES, 'UTF-8');
        $favicon = '/__pionia/favicon.ico';
        $scalarConfig = htmlspecialchars(
            json_encode([
                'theme' => 'purple',
                'layout' => 'modern',
                'hideDownloadButton' => false,
                'darkMode' => true,
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
<script
    id="api-reference"
    data-url="{$specUrl}"
    data-configuration='{$scalarConfig}'></script>
<script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
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
