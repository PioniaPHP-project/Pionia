<?php

namespace Pionia\Documentation;

use Pionia\Documentation\Contracts\ActionDoc;
use Pionia\Documentation\Contracts\MoonlightApiCatalog;
use Pionia\Documentation\Contracts\ServiceDoc;

class MoonlightCatalogExporter
{
    public function toArray(MoonlightApiCatalog $catalog): array
    {
        $result = [];

        foreach ($catalog->versions as $version => $services) {
            $result[$version] = [];

            foreach ($services as $alias => $service) {
                $result[$version][$alias] = $this->servicePayload($service);
            }
        }

        return $result;
    }

    public function toJson(MoonlightApiCatalog $catalog, int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string
    {
        return json_encode($this->toArray($catalog), $flags | JSON_THROW_ON_ERROR) . "\n";
    }

    public function toHtml(MoonlightApiCatalog $catalog, string $jsonPayload): string
    {
        $title = htmlspecialchars($catalog->title . ' — Moonlight catalog', ENT_QUOTES, 'UTF-8');
        $json = htmlspecialchars($jsonPayload, ENT_NOQUOTES, 'UTF-8');
        $ping = htmlspecialchars(apiPingPath(), ENT_QUOTES, 'UTF-8');
        $rawJson = htmlspecialchars(apiCatalogPath(), ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$title}</title>
    <style>
        body { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; background: #0f172a; color: #e2e8f0; margin: 0; padding: 1.5rem; }
        h1 { font-family: Inter, system-ui, sans-serif; font-size: 1.25rem; margin: 0 0 0.5rem; }
        p { font-family: Inter, system-ui, sans-serif; color: #94a3b8; margin: 0 0 1rem; }
        a { color: #38bdf8; }
        pre { background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1rem; overflow: auto; white-space: pre-wrap; word-break: break-word; }
    </style>
</head>
<body>
    <h1>{$title}</h1>
    <p>Debug-only Moonlight catalog. JSON: <a href="{$rawJson}">{$rawJson}</a> · Ping: <a href="{$ping}">{$ping}</a></p>
    <pre>{$json}</pre>
</body>
</html>
HTML;
    }

    /**
     * @return array<string, mixed>
     */
    private function servicePayload(ServiceDoc $service): array
    {
        $payload = [
            'class' => $service->className,
            'actions' => [],
        ];

        if ($service->summary) {
            $payload['summary'] = $service->summary;
        }

        if ($service->table) {
            $payload['table'] = $service->table;
        }

        if ($service->auth) {
            $payload['auth'] = $service->auth;
        }

        foreach ($service->actions as $action) {
            $payload['actions'][$action->name] = $this->actionPayload($action);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function actionPayload(ActionDoc $action): array
    {
        $payload = [];

        if ($action->summary) {
            $payload['summary'] = $action->summary;
        }

        if ($action->auth) {
            $payload['auth'] = $action->auth;
        }

        if ($action->permissions !== []) {
            $payload['permissions'] = $action->permissions;
        }

        if ($action->params !== []) {
            $payload['params'] = $action->params;
        }

        if ($action->returnShape) {
            $payload['return'] = $action->returnShape;
        }

        if ($action->example) {
            $payload['example'] = $action->example;
        }

        if ($action->deprecated) {
            $payload['deprecated'] = true;
        }

        return $payload;
    }
}
