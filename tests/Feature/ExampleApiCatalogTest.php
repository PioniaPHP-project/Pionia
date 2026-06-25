<?php

namespace Feature;

use Pionia\TestSuite\Concerns\InteractsWithTestEnvironment;
use Pionia\TestSuite\PioniaTestCase;

class ExampleApiCatalogTest extends PioniaTestCase
{
    use InteractsWithTestEnvironment;

    protected function tearDown(): void
    {
        $this->clearDocsEnv();
        parent::tearDown();
    }

    public function testCatalogEndpointReturnsJsonInDebugMode(): void
    {
        $this->setDebugEnv(true);
        $this->clearDocsEnv();

        $response = $this->get(apiCatalogPath(), [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(200, $response->status());
        $payload = $response->json();
        $this->assertArrayHasKey('v1', $payload);
        $this->assertArrayHasKey('auth', $payload['v1']);
        $this->assertArrayHasKey('actions', $payload['v1']['auth']);
    }

    public function testCatalogEndpointHiddenWhenNotEnabled(): void
    {
        $this->setDebugEnv(false);
        $this->setDocsEnv(false);

        $response = $this->get(apiCatalogPath(), [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $payload = $response->json();

        $this->assertGreaterThan(0, $payload['returnCode']);
    }

    public function testCatalogEndpointReturnsHtmlForBrowserAccept(): void
    {
        $this->setDebugEnv(true);
        $this->clearDocsEnv();

        $response = $this->get(apiCatalogPath(), [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);

        $this->assertSame(302, $response->status());
        $this->assertSame('/docs', $response->header('Location'));
    }

    public function testCatalogEndpointWorksWithTrailingSlash(): void
    {
        $this->setDebugEnv(true);
        $this->clearDocsEnv();

        $response = $this->get(apiCatalogPath() . '/', [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(200, $response->status());
        $this->assertArrayHasKey('v1', $response->json());
    }
}
