<?php

namespace Pionia\Documentation;

use Pionia\Documentation\Contracts\ActionDoc;
use Pionia\Documentation\Contracts\MoonlightApiCatalog;
use Pionia\Documentation\Contracts\ServiceDoc;

/**
 * Exports a Moonlight catalog as OpenAPI 3.1.
 *
 * Runtime truth: one `POST` per version (`/api/v1/`) with `{ service, action, … }`.
 * OpenAPI permits only one operation for a method/path pair, so actions are represented
 * as request schemas and named examples on the real version dispatch operation.
 */
class OpenApiExporter
{
    public function export(MoonlightApiCatalog $catalog): string
    {
        $paths = [];
        $tagDefinitions = [];

        foreach ($catalog->versions as $version => $services) {
            $dispatchPath = apiVersionPath($version);
            ksort($services);

            $oneOf = [];
            $examples = [];
            $catalogLines = [
                'Send `POST ' . $dispatchPath . '` with `{ "service", "action", ...params }`.',
                '',
                'Choose a named request example for the action you want to call.',
                '',
            ];

            foreach ($services as $service) {
                $catalogLines[] = '### ' . $this->displayName($service->alias);
                $catalogLines[] = '';

                foreach ($service->actions as $action) {
                    $key = $service->alias . '.' . $action->name;
                    $schema = $this->requestSchema($service, $action);
                    $schema['title'] = $key;
                    $oneOf[] = $schema;
                    $examples[$key] = [
                        'summary' => ($action->summary ?? $action->name) . ' (`' . $key . '`)',
                        'value' => $this->requestExampleValue($service, $action),
                    ];
                    $catalogLines[] = '- `' . $key . '`'
                        . ($action->summary ? ' — ' . $action->summary : '');
                }
                $catalogLines[] = '';
            }

            $tag = 'API ' . $version;
            $tagDefinitions[] = [
                'name' => $tag,
                'description' => 'Dispatch endpoint for API version `' . $version . '`.',
                'x-displayName' => $tag,
            ];

            $paths[$dispatchPath] = [
                'post' => [
                    'operationId' => $this->sanitizeKey('moonlight_' . $version),
                    'summary' => 'Dispatch ' . $version . ' action',
                    'description' => implode("\n", $catalogLines),
                    'tags' => [$tag],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['oneOf' => $oneOf],
                                'examples' => $examples,
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Pionia JSON envelope',
                            'content' => [
                                'application/json' => [
                                    'schema' => $this->responseSchema(),
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        }

        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $catalog->title . ' Moonlight API',
                'version' => '1.0.0',
                'description' => 'Moonlight exposes one POST endpoint per API version. '
                    . 'Select a named request example for a service action, then send it to the versioned API path.',
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
    private function responseSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['returnCode', 'returnMessage'],
            'properties' => [
                'returnCode' => ['type' => 'integer'],
                'returnMessage' => ['type' => 'string'],
                'returnData' => ['type' => 'object', 'nullable' => true],
                'extraData' => ['type' => 'object', 'nullable' => true],
            ],
        ];
    }

    private function sanitizeKey(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $value) ?? $value;
    }

    private function displayName(string $alias): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $alias));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestExampleValue(ServiceDoc $service, ActionDoc $action): array
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

        return $value;
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
