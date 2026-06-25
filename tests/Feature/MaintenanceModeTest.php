<?php

namespace Feature;

use Pionia\TestSuite\Concerns\InteractsWithTestEnvironment;
use Pionia\TestSuite\PioniaTestCase;

class MaintenanceModeTest extends PioniaTestCase
{
    use InteractsWithTestEnvironment;

    protected function tearDown(): void
    {
        $this->clearMaintenanceEnv();
        parent::tearDown();
    }

    public function testMaintenanceModeReturnsHtmlPageForBrowsers(): void
    {
        $this->setMaintenanceEnv(true, 'Database migration in progress.');

        $response = $this->get('/', [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);

        $this->assertSame(503, $response->status());
        $this->assertStringContainsString('text/html', (string) $response->header('Content-Type'));
        $this->assertStringContainsString('Database migration in progress.', $response->content());
        $this->assertStringContainsString('data-pionia-theme-toggle', $response->content());
    }

    public function testMaintenanceModeReturnsJsonForApiClients(): void
    {
        $this->setMaintenanceEnv(true, 'Database migration in progress.');

        $response = $this->post('/api/v1/', [
            'service' => 'auth',
            'action' => 'list_auth',
        ], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertSame(503, $response->status());
        $payload = $response->json();
        $this->assertSame(503, $payload['returnCode']);
        $this->assertStringContainsString('Database migration in progress.', (string) $payload['returnMessage']);
    }

    public function testMaintenanceBypassTokenAllowsTraffic(): void
    {
        $this->setMaintenanceEnv(true, 'Offline', 'secret-bypass');

        $blocked = $this->get('/');
        $this->assertSame(503, $blocked->status());

        $allowed = $this->get('/?bypass=secret-bypass');
        $this->assertSame(200, $allowed->status());
    }

    public function testFrameworkAssetsStillServedDuringMaintenance(): void
    {
        $this->setMaintenanceEnv(true);

        $response = $this->get('/__pionia/pionia-theme.css');

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('text/css', (string) $response->header('Content-Type'));
    }
}
