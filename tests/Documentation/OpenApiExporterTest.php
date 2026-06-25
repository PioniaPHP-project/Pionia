<?php

namespace Documentation;

use Pionia\Documentation\MoonlightDocCollector;
use Pionia\Documentation\OpenApiExporter;
use Pionia\TestSuite\PioniaTestCase;

class OpenApiExporterTest extends PioniaTestCase
{
    public function testExportProducesValidOpenApi31WithMoonlightSchemas(): void
    {
        $catalog = (new MoonlightDocCollector())->collect();
        $json = (new OpenApiExporter())->export($catalog);
        $spec = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertStringContainsString('Moonlight API', $spec['info']['title']);
        $this->assertArrayHasKey('/api/v1', $spec['paths']);
        $this->assertArrayHasKey('auth.list_auth', $spec['components']['schemas']);

        $schema = $spec['components']['schemas']['auth.list_auth'];
        $this->assertSame('auth', $schema['properties']['service']['const']);
        $this->assertSame('list_auth', $schema['properties']['action']['const']);
    }
}
