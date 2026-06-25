<?php

namespace Pionia\Documentation;

use Pionia\Documentation\Contracts\ActionDoc;
use Pionia\Documentation\Contracts\MoonlightApiCatalog;
use Pionia\Documentation\Contracts\ServiceDoc;

class OpenApiExporter
{
    public function export(MoonlightApiCatalog $catalog): string
    {
        $schemas = [];
        $oneOf = [];

        foreach ($catalog->versions as $version => $services) {
            foreach ($services as $service) {
                foreach ($service->actions as $action) {
                    $schemaName = $this->schemaName($service->alias, $action->name);
                    $schemas[$schemaName] = $this->actionSchema($service, $action);
                    $oneOf[] = ['$ref' => '#/components/schemas/' . $schemaName];
                }
            }
        }

        $paths = [];
        foreach ($catalog->versions as $version => $services) {
            $postPath = rtrim(apiVersionPath($version), '/');
            $getPath = rtrim(apiVersionPath($version), '/') . '/{service}/{action}/';

            $paths[$postPath] = [
                'post' => [
                    'summary' => 'Moonlight dispatch (POST)',
                    'tags' => ['Moonlight'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['oneOf' => $oneOf],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/PioniaEnvelope'],
                    ],
                ],
            ];

            $paths[$getPath] = [
                'get' => [
                    'summary' => 'Moonlight dispatch (GET)',
                    'tags' => ['Moonlight'],
                    'parameters' => [
                        ['name' => 'service', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                        ['name' => 'action', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']],
                    ],
                    'responses' => [
                        '200' => ['$ref' => '#/components/responses/PioniaEnvelope'],
                    ],
                ],
            ];
        }

        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $catalog->title . ' Moonlight API',
                'version' => '1.0.0',
                'description' => 'Pionia Moonlight profile — dispatch via { service, action, ...params } on versioned API paths.',
            ],
            'paths' => $paths,
            'components' => [
                'schemas' => array_merge([
                    'PioniaEnvelope' => [
                        'type' => 'object',
                        'required' => ['returnCode', 'returnMessage'],
                        'properties' => [
                            'returnCode' => ['type' => 'integer'],
                            'returnMessage' => ['type' => 'string'],
                            'returnData' => ['type' => 'object', 'nullable' => true],
                            'extraData' => ['type' => 'object', 'nullable' => true],
                        ],
                    ],
                ], $schemas),
                'responses' => [
                    'PioniaEnvelope' => [
                        'description' => 'Pionia JSON envelope',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/PioniaEnvelope'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        return json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function actionSchema(ServiceDoc $service, ActionDoc $action): array
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

    private function schemaName(string $service, string $action): string
    {
        return preg_replace('/[^a-zA-Z0-9_.-]/', '_', $service . '.' . $action) ?? ($service . '.' . $action);
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
