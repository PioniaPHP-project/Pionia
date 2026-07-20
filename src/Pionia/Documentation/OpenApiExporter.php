<?php

namespace Pionia\Documentation;

use Pionia\Documentation\Contracts\ActionDoc;
use Pionia\Documentation\Contracts\MoonlightApiCatalog;
use Pionia\Documentation\Contracts\ServiceDoc;

class OpenApiExporter
{
    public function export(MoonlightApiCatalog $catalog): string
    {
        $paths = [];
        $tagDefinitions = [];

        foreach ($catalog->versions as $version => $services) {
            $dispatchPath = rtrim(apiVersionPath($version), '/');

            ksort($services);

            foreach ($services as $service) {
                $tag = $service->alias;

                $tagDefinitions[] = [
                    'name' => $tag,
                    'description' => $service->summary ?? ('`' . $tag . '` service'),
                    'x-displayName' => $this->displayName($tag),
                ];

                foreach ($service->actions as $action) {
                    $docPath = $this->documentPath($version, $service->alias, $action->name);
                    $paths[$docPath] = [
                        'post' => [
                            'operationId' => $this->operationId($service->alias, $action->name),
                            'summary' => $action->summary ?? $action->name,
                            'description' => $this->operationDescription($service, $action, $dispatchPath, $version),
                            'tags' => [$tag],
                            'requestBody' => [
                                'required' => true,
                                'content' => [
                                    'application/json' => [
                                        'schema' => $this->requestSchema($service, $action),
                                        'examples' => $this->requestExamples($service, $action),
                                    ],
                                ],
                            ],
                            'responses' => [
                                '200' => [
                                    'description' => 'Pionia JSON envelope',
                                    'content' => [
                                        'application/json' => [
                                            'schema' => $this->responseSchema($service, $action),
                                        ],
                                    ],
                                ],
                            ],
                            'x-pionia-dispatch' => [
                                'method' => 'POST',
                                'url' => $dispatchPath,
                                'service' => $service->alias,
                                'action' => $action->name,
                            ],
                        ],
                    ];

                    if ($action->deprecated) {
                        $paths[$docPath]['post']['deprecated'] = true;
                    }
                }
            }
        }

        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $catalog->title . ' Moonlight API',
                'version' => '1.0.0',
                'description' => 'Browse services and actions in the sidebar. Each page documents one Moonlight action with inline request and response shapes. '
                    . 'Dispatch all actions with `POST` to the versioned API base and body `{ "service", "action", ...params }`. '
                    . 'Path fragments (`#service.action`) are for documentation navigation only; the sandbox posts to the real base URL.',
            ],
            'tags' => $tagDefinitions,
            'paths' => $paths,
        ];

        return json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function requestSchema(ServiceDoc $service, ActionDoc $action): array
    {
        $properties = [
            'service' => ['const' => $service->alias],
            'action' => ['const' => $action->name],
        ];

        foreach ($action->params as $name => $meta) {
            $properties[$name] = [
                'type' => $this->openApiType($meta['type'] ?? 'string'),
                'description' => $meta['description'] ?? '',
            ];
        }

        $schema = [
            'type' => 'object',
            'required' => ['service', 'action'],
            'properties' => $properties,
        ];

        if ($action->summary) {
            $schema['description'] = $action->summary;
        }

        if ($action->deprecated) {
            $schema['deprecated'] = true;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(ServiceDoc $service, ActionDoc $action): array
    {
        $returnData = ['type' => 'object', 'nullable' => true];

        if ($action->returnShape) {
            $returnData['description'] = $action->returnShape;
        }

        return [
            'type' => 'object',
            'required' => ['returnCode', 'returnMessage'],
            'properties' => [
                'returnCode' => ['type' => 'integer'],
                'returnMessage' => ['type' => 'string'],
                'returnData' => $returnData,
                'extraData' => ['type' => 'object', 'nullable' => true],
            ],
        ];
    }

    /**
     * Unique OpenAPI path key: real dispatch URL + fragment for per-action navigation.
     * Fragments are not sent over HTTP; Scalar try-it-out rewrites to x-pionia-dispatch.url.
     */
    private function documentPath(string $version, string $service, string $action): string
    {
        return rtrim(apiVersionPath($version), '/') . '#' . $service . '.' . $action;
    }

    private function operationId(string $service, string $action): string
    {
        return $this->sanitizeKey($service . '_' . $action);
    }

    private function sanitizeKey(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $value) ?? $value;
    }

    private function displayName(string $alias): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $alias));
    }

    private function operationDescription(ServiceDoc $service, ActionDoc $action, string $dispatchPath, string $version): string
    {
        $lines = [
            $action->summary ?? ('Invoke `' . $action->name . '` on `' . $service->alias . '`.'),
            '',
            '**Dispatch**',
            '',
            '| | |',
            '|---|---|',
            '| URL | `' . $dispatchPath . '` |',
            '| Method | `POST` |',
            '| `service` | `' . $service->alias . '` |',
            '| `action` | `' . $action->name . '` |',
            '| Auth | `' . ($action->auth ?? $service->auth ?? 'none') . '` |',
        ];

        if ($action->permissions !== []) {
            $lines[] = '| Permissions | `' . implode('`, `', $action->permissions) . '` |';
        }

        if ($service->className) {
            $lines[] = '| PHP class | `' . $service->className . '` |';
        }

        if ($service->table) {
            $lines[] = '| Table | `' . $service->table . '` |';
        }

        $lines[] = '';
        $lines[] = 'Health check: `GET ' . apiPingPath($version) . '`.';

        return implode("\n", $lines);
    }

    /**
     * @return array<string, array{summary?: string, value: mixed}>
     */
    private function requestExamples(ServiceDoc $service, ActionDoc $action): array
    {
        $value = [
            'service' => $service->alias,
            'action' => $action->name,
        ];

        $payload = $action->example;
        if (is_string($payload) && $payload !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $value = array_merge($value, $decoded);
                $value['service'] = $decoded['service'] ?? $service->alias;
                $value['action'] = $decoded['action'] ?? $action->name;
            }
        }

        foreach ($action->params as $param => $meta) {
            if (!array_key_exists($param, $value)) {
                $value[$param] = $this->placeholderForType($meta['type'] ?? 'string');
            }
        }

        return [
            'default' => [
                'summary' => $action->name,
                'value' => $value,
            ],
        ];
    }

    private function placeholderForType(string $type): mixed
    {
        return match (strtolower($type)) {
            'int', 'integer' => 0,
            'float', 'double', 'number' => 0.0,
            'bool', 'boolean' => true,
            'array', 'list' => [],
            'object' => (object) [],
            default => '',
        };
    }

    private function openApiType(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => 'integer',
            'float', 'double', 'number' => 'number',
            'bool', 'boolean' => 'boolean',
            'array', 'list' => 'array',
            'object' => 'object',
            default => 'string',
        };
    }
}
