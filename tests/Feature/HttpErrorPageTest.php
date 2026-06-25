<?php

namespace Feature;

use Pionia\TestSuite\Concerns\InteractsWithTestEnvironment;
use Pionia\TestSuite\PioniaTestCase;

class HttpErrorPageTest extends PioniaTestCase
{
    use InteractsWithTestEnvironment;

    public function testUnknownRouteReturnsHtmlPageForBrowser(): void
    {
        $response = $this->get('/this-route-does-not-exist', [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);

        $this->assertSame(404, $response->status());
        $this->assertStringContainsString('text/html', (string) $response->header('Content-Type'));
        $this->assertStringContainsString('not found', strtolower($response->content()));
        $this->assertStringNotContainsString('ResourceNotFoundException', $response->content());
    }

    public function testUnknownRouteReturnsCleanJsonForApiClients(): void
    {
        $response = $this->get('/api/v1/not-a-route', [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(404, $response->status());
        $payload = $response->json();
        $this->assertSame(404, $payload['returnCode']);
        $this->assertNull($payload['returnData']);
    }

    public function testGetApiVersionRootReturnsOverviewJson(): void
    {
        $response = $this->get('/api/v1/', [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(200, $response->status());
        $payload = $response->json();
        $this->assertSame(0, $payload['returnCode']);
        $this->assertSame('v1', $payload['returnData']['version']);
        $this->assertSame('POST', $payload['returnData']['dispatch']['method']);
    }

    public function testGetApiVersionRootWithoutSlashReturnsOverview(): void
    {
        $response = $this->get('/api/v1', [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(200, $response->status());
        $this->assertSame(0, $response->json()['returnCode']);
    }

    public function testGetApiVersionRootRedirectsBrowserToDocsWhenEnabled(): void
    {
        $this->setDebugEnv(true);
        $this->clearDocsEnv();

        $response = $this->get('/api/v1/', [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);

        $this->assertSame(302, $response->status());
        $this->assertSame('/docs', $response->header('Location'));
    }
}
