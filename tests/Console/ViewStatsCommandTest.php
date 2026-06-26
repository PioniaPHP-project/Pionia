<?php

namespace Console;

use Pionia\Http\Monitoring\RequestMetrics;
use Pionia\TestSuite\PioniaTestCase;

class ViewStatsCommandTest extends PioniaTestCase
{
    protected function tearDown(): void
    {
        RequestMetrics::reset();
        parent::tearDown();
    }

    public function testStatsViewCommandExitsSuccessfully(): void
    {
        RequestMetrics::reset();
        $code = $this->artisan('stats:view');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Request performance', $this->consoleOutput());
    }

    public function testStatsViewJsonOutput(): void
    {
        $code = $this->artisan('stats:view', ['--json' => true]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('"health"', $this->consoleOutput());
        $this->assertStringContainsString('"request_metrics"', $this->consoleOutput());
    }
}
