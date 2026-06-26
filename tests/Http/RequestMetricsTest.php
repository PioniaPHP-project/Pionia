<?php

namespace Http;

use Pionia\Http\Monitoring\RequestMetrics;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;
use Symfony\Component\HttpFoundation\Response;

class RequestMetricsTest extends PioniaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RequestMetrics::reset();
    }

    protected function tearDown(): void
    {
        RequestMetrics::reset();
        parent::tearDown();
    }

    public function testRecordsApiRequestAndBuildsSnapshot(): void
    {
        $request = Request::create('/api/v1/', 'POST', [
            'service' => 'auth',
            'action' => 'list_auth',
        ]);
        $response = new Response('ok', 200);

        RequestMetrics::record($request, $response, 42.5);
        RequestMetrics::record($request, $response, 18.0);

        $snapshot = RequestMetrics::snapshot(5);

        $this->assertSame(2, $snapshot['total_requests']);
        $this->assertSame(30.25, $snapshot['avg_duration_ms']);
        $this->assertCount(1, $snapshot['high_traffic_endpoints']);
        $this->assertSame('auth::list_auth', $snapshot['high_traffic_endpoints'][0]['label']);
        $this->assertSame(2, $snapshot['high_traffic_endpoints'][0]['count']);
        $this->assertSame(30.25, $snapshot['high_traffic_endpoints'][0]['avg_ms']);
    }

    public function testHeavyEndpointsSortedByAverageDuration(): void
    {
        $fast = Request::create('/api/v1/', 'POST', ['service' => 'auth', 'action' => 'ping']);
        $slow = Request::create('/api/v1/', 'POST', ['service' => 'auth', 'action' => 'list_auth']);

        RequestMetrics::record($fast, new Response(), 5.0);
        RequestMetrics::record($slow, new Response(), 120.0);

        $heavy = RequestMetrics::snapshot(5)['heavy_endpoints'];

        $this->assertSame('auth::list_auth', $heavy[0]['label']);
        $this->assertSame('auth::ping', $heavy[1]['label']);
    }

    public function testSkipsFrameworkStaticAssets(): void
    {
        $request = Request::create('/__pionia/welcome.css', 'GET');
        RequestMetrics::record($request, new Response(), 1.0);

        $this->assertSame(0, RequestMetrics::snapshot()['total_requests']);
    }
}
