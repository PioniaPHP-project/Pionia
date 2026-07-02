<?php

namespace Documentation;

use Pionia\Documentation\MoonlightDocCollector;
use Pionia\Documentation\OpenApiExporter;
use Pionia\TestSuite\PioniaTestCase;

class OpenApiExporterTest extends PioniaTestCase
{
    public function testExportProducesServiceActionNavigationOnly(): void
    {
        $catalog = (new MoonlightDocCollector())->collect();
        $json = (new OpenApiExporter())->export($catalog);
        $spec = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertStringContainsString('Moonlight API', $spec['info']['title']);
        $this->assertArrayNotHasKey('x-tagGroups', $spec);
        $this->assertArrayNotHasKey('components', $spec);
        $this->assertArrayNotHasKey('/api/v1', $spec['paths']);
        $this->assertArrayHasKey('/api/v1/moonlight/auth/list_auth', $spec['paths']);

        $operation = $spec['paths']['/api/v1/moonlight/auth/list_auth']['post'];
        $this->assertSame(['auth'], $operation['tags']);
        $this->assertSame('auth_list_auth', $operation['operationId']);
        $this->assertArrayHasKey('x-pionia-dispatch', $operation);
        $this->assertSame('/api/v1', $operation['x-pionia-dispatch']['url']);

        $requestSchema = $operation['requestBody']['content']['application/json']['schema'];
        $this->assertSame('auth', $requestSchema['properties']['service']['const']);
        $this->assertSame('list_auth', $requestSchema['properties']['action']['const']);

        $responseSchema = $operation['responses']['200']['content']['application/json']['schema'];
        $this->assertArrayHasKey('returnData', $responseSchema['properties']);
    }
}
