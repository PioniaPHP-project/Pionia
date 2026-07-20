<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class BenchCommandTest extends PioniaTestCase
{
    public function testBenchCommandExitsSuccessfully(): void
    {
        $code = $this->artisan('bench', [
            '--iterations' => '3',
            '--warmup' => '1',
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('benchmarks', strtolower($this->consoleOutput()));
    }

    public function testBenchJsonOutput(): void
    {
        $code = $this->artisan('bench', [
            '--iterations' => '2',
            '--warmup' => '0',
            '--json' => true,
        ]);

        $this->assertSame(0, $code);
        $payload = json_decode($this->consoleOutput(), true);
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('results', $payload);
        $this->assertNotEmpty($payload['results']);
        $this->assertArrayHasKey('mean_ms', $payload['results'][0]);
    }
}
