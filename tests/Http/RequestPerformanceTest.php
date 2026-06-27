<?php

namespace Http;

use Pionia\Http\Monitoring\RequestMetrics;
use Pionia\TestSuite\Concerns\MakesHttpRequests;
use Pionia\TestSuite\PioniaTestCase;

class RequestPerformanceTest extends PioniaTestCase
{
    use MakesHttpRequests;

    protected function tearDown(): void
    {
        RequestMetrics::reset();
        parent::tearDown();
    }

    public function testPingRequestRecordsSubSecondAverage(): void
    {
        RequestMetrics::reset();

        for ($i = 0; $i < 5; $i++) {
            $this->getApiPing();
        }

        $snapshot = RequestMetrics::snapshot();

        $this->assertGreaterThanOrEqual(5, $snapshot['total_requests']);
        $this->assertLessThan(500, $snapshot['avg_duration_ms']);
    }

    public function testApiActionDispatchStaysUnderReasonableLatency(): void
    {
        RequestMetrics::reset();

        $this->postApi('auth', 'list_auth');

        $snapshot = RequestMetrics::snapshot();
        $endpoint = $snapshot['high_traffic_endpoints'][0] ?? null;

        $this->assertNotNull($endpoint);
        $this->assertSame('auth::list_auth', $endpoint['label']);
        $this->assertLessThan(500, $endpoint['avg_ms']);
    }
}
