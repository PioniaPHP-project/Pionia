<?php

namespace Http;

use Pionia\Http\Pages\DeveloperStatsCollector;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;

class DeveloperStatsCollectorTest extends PioniaTestCase
{
    public function testCollectIncludesHealthAndSystemSections(): void
    {
        $payload = (new DeveloperStatsCollector(realm(), Request::create('/stats', 'GET')))->collect();

        $this->assertArrayHasKey('health', $payload);
        $this->assertArrayHasKey('system', $payload);
        $this->assertArrayHasKey('memory', $payload['system']);
        $this->assertArrayHasKey('volume', $payload['system']);
        $this->assertSame('filesystem_volume', $payload['system']['volume']['scope']);
        $this->assertMatchesRegularExpression(
            '/^Volume: \d+(\.\d+)?\/\d+(\.\d+)? ~ \d+(\.\d+)?% used$/',
            $payload['health']['checks']['disk']['message'],
        );
        $this->assertSame(
            $payload['health']['checks']['disk']['message'],
            $payload['system']['volume']['usage_summary'],
        );
        $this->assertContains($payload['health']['checks']['database']['status'], ['ok', 'critical']);
    }
}
