<?php

namespace Documentation;

use Pionia\Documentation\MoonlightDocCollector;
use Pionia\Documentation\OpenApiExporter;
use Pionia\TestSuite\PioniaTestCase;

class OpenApiExporterTest extends PioniaTestCase
{
    public function testExportUsesOnlyRealDispatchPathWithEveryActionAsAnExample(): void
    {
        $catalog = (new MoonlightDocCollector())->collect();
        $json = (new OpenApiExporter())->export($catalog);
        $spec = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertStringContainsString('Moonlight API', $spec['info']['title']);
        $this->assertArrayNotHasKey('x-tagGroups', $spec);
        $this->assertSame(['/api/v1/'], array_keys($spec['paths']));

        $operation = $spec['paths']['/api/v1/']['post'];
        $this->assertSame(['API v1'], $operation['tags']);
        $this->assertSame('moonlight_v1', $operation['operationId']);

        $content = $operation['requestBody']['content']['application/json'];
        $this->assertArrayHasKey('oneOf', $content['schema']);
        $this->assertArrayHasKey('auth.list_auth', $content['examples']);
        $this->assertArrayHasKey('auth.create_auth', $content['examples']);
        $this->assertArrayHasKey('auth.delete_auth', $content['examples']);
        $this->assertArrayHasKey('auth.get_auth', $content['examples']);
        $this->assertArrayHasKey('auth.update_auth', $content['examples']);
        $this->assertSame('auth', $content['examples']['auth.list_auth']['value']['service']);
        $this->assertSame('list_auth', $content['examples']['auth.list_auth']['value']['action']);

        $actionCount = 0;
        foreach ($catalog->versions as $services) {
            foreach ($services as $service) {
                $actionCount += count($service->actions);
            }
        }
        $this->assertSame($actionCount, count($content['schema']['oneOf']));
        $this->assertSame($actionCount, count($content['examples']));
        $this->assertGreaterThan(1, $actionCount);
    }
}
