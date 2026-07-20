<?php

namespace Feature;

use Pionia\TestSuite\Concerns\InteractsWithTestEnvironment;
use Pionia\TestSuite\PioniaTestCase;

class ExampleApiDocsTest extends PioniaTestCase
{
    use InteractsWithTestEnvironment;

    protected function tearDown(): void
    {
        $this->clearDocsEnv();
        parent::tearDown();
    }

    public function testDocsPageReturnsScalarUiInDebugMode(): void
    {
        $this->setDebugEnv(true);
        $this->clearDocsEnv();

        $response = $this->get('/docs');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('text/html', (string) $response->header('Content-Type'));
        $this->assertStringContainsString('scalar/api-reference', $response->content());
        $this->assertStringNotContainsString('data-pionia-theme-toggle', $response->content());
        $this->assertStringContainsString('/docs/openapi.json', $response->content());
    }

    public function testOpenApiSpecEndpointReturnsValidOpenApi(): void
    {
        $this->setDebugEnv(true);
        $this->clearDocsEnv();

        $response = $this->get('/docs/openapi.json', [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(200, $response->status());
        $spec = $response->json();
        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('/api/v1#auth.list_auth', $spec['paths']);
    }

    public function testDocsHiddenWhenNotEnabled(): void
    {
        $this->setDebugEnv(false);
        $this->setDocsEnv(false);

        $response = $this->get('/docs');

        $this->assertSame(404, $response->status());
        $this->assertStringContainsString('data-pionia-theme-toggle', $response->content());
        $this->assertStringContainsString('API docs are not available', $response->content());
    }

    public function testDocsAvailableWhenExplicitlyEnabledWithoutDebug(): void
    {
        $this->setDebugEnv(false);
        $this->setDocsEnv(true);

        $response = $this->get('/docs');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('scalar/api-reference', $response->content());
    }

    public function testDocsRequireTokenWhenConfigured(): void
    {
        $this->setDebugEnv(true);
        $this->setDocsEnv(null, 'secret-docs');

        $denied = $this->get('/docs');
        $this->assertSame(401, $denied->status());
        $this->assertStringContainsString('data-pionia-theme-toggle', $denied->content());
        $this->assertStringContainsString('Docs access denied', $denied->content());

        $allowed = $this->get('/docs?token=secret-docs');
        $this->assertSame(200, $allowed->status());
        $this->assertStringContainsString('/docs/openapi.json?token=secret-docs', $allowed->content());
    }

    public function testOpenApiSpecRequiresMatchingToken(): void
    {
        $this->setDebugEnv(true);
        $this->setDocsEnv(null, 'secret-docs');

        $denied = $this->get('/docs/openapi.json', ['HTTP_ACCEPT' => 'application/json']);
        $this->assertSame(401, $denied->status());

        $allowed = $this->get('/docs/openapi.json?token=secret-docs', [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $this->assertSame(200, $allowed->status());
    }
}
