<?php

namespace Feature;

use Pionia\Http\Monitoring\RequestMetrics;
use Pionia\TestSuite\Concerns\InteractsWithTestEnvironment;
use Pionia\TestSuite\PioniaTestCase;

class DeveloperStatsTest extends PioniaTestCase
{
    use InteractsWithTestEnvironment;

    protected function tearDown(): void
    {
        RequestMetrics::reset();
        $this->clearStatsEnv();
        parent::tearDown();
    }

    public function testStatsPageAvailableInDebugMode(): void
    {
        $this->setDebugEnv(true);
        $this->clearStatsEnv();

        $response = $this->get('/stats');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('Developer stats', $response->content());
        $this->assertStringContainsString('health-grid', $response->content());
        $this->assertStringContainsString('data-pionia-theme-toggle', $response->content());
        $this->assertStringContainsString('Environment', $response->content());
    }

    public function testStatsJsonReturnsHealthPayload(): void
    {
        $this->setDebugEnv(true);
        $this->clearStatsEnv();

        $response = $this->get('/stats.json', [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(200, $response->status());
        $payload = $response->json();
        $this->assertArrayHasKey('health', $payload);
        $this->assertArrayHasKey('system', $payload);
        $this->assertArrayHasKey('application', $payload);
        $this->assertArrayHasKey('request_metrics', $payload);
    }

    public function testStatsJsonIncludesRequestMetricsAfterTraffic(): void
    {
        $this->setDebugEnv(true);
        $this->clearStatsEnv();
        RequestMetrics::reset();

        $this->getApiPing();
        $this->postApi('auth', 'list_auth');

        $response = $this->get('/stats.json', ['HTTP_ACCEPT' => 'application/json']);
        $payload = $response->json();

        $this->assertGreaterThanOrEqual(2, $payload['request_metrics']['total_requests'] ?? 0);
        $this->assertArrayHasKey('heavy_endpoints', $payload['request_metrics']);
        $this->assertArrayHasKey('high_traffic_endpoints', $payload['request_metrics']);
    }

    public function testStatsPageShowsTrafficTab(): void
    {
        $this->setDebugEnv(true);
        $this->clearStatsEnv();

        $response = $this->get('/stats');

        $this->assertStringContainsString('traffic-panel', $response->content());
        $this->assertStringContainsString('traffic-heavy-panel', $response->content());
        $this->assertStringContainsString('traffic-api-panel', $response->content());
        $this->assertStringContainsString('Heaviest endpoints (avg ms)', $response->content());
        $this->assertStringContainsString('API traffic (service · action)', $response->content());
    }

    public function testStatsHiddenWhenNotEnabled(): void
    {
        $this->setDebugEnv(false);
        $this->setStatsEnv(false);

        $response = $this->get('/stats');

        $this->assertSame(404, $response->status());
        $this->assertStringContainsString('data-pionia-theme-toggle', $response->content());
        $this->assertStringContainsString('Developer stats are not available', $response->content());
    }

    public function testStatsRequireSeparateTokenFromDocs(): void
    {
        $this->setDebugEnv(true);
        $this->setStatsEnv(null, 'stats-secret');
        $this->setDocsEnv(null, 'docs-secret');

        $denied = $this->get('/stats');
        $this->assertSame(401, $denied->status());
        $this->assertStringContainsString('data-pionia-theme-toggle', $denied->content());
        $this->assertStringContainsString('Stats access denied', $denied->content());

        $docsTokenFails = $this->get('/stats?token=docs-secret');
        $this->assertSame(401, $docsTokenFails->status());

        $allowed = $this->get('/stats?token=stats-secret');
        $this->assertSame(200, $allowed->status());
    }
}
