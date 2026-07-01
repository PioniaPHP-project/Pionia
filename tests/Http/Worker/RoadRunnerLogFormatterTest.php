<?php

namespace Http\Worker;

use Pionia\Http\Worker\RoadRunnerLogFormatter;
use Pionia\Http\Worker\RoadRunnerLogLineWriter;
use Pionia\TestSuite\PioniaTestCase;

class RoadRunnerLogFormatterTest extends PioniaTestCase
{
    public function testFormatsHttpAccessLogLine(): void
    {
        $line = '2026-07-01T13:18:42+0000        INFO    http            http log        '
            . '{"status": 200, "method": "GET", "URI": "/__pionia/pionia_logo.webp", '
            . '"write_bytes": 21988, "elapsed": 8}';

        $formatted = RoadRunnerLogFormatter::format($line);

        $this->assertStringContainsString('13:18:42', $formatted);
        $this->assertStringContainsString('GET', $formatted);
        $this->assertStringContainsString('/__pionia/pionia_logo.webp', $formatted);
        $this->assertStringContainsString('<info>200</info>', $formatted);
        $this->assertStringContainsString('21.5 KB', $formatted);
        $this->assertStringContainsString('8ms', $formatted);
        $this->assertStringNotContainsString('"write_bytes"', $formatted);
    }

    public function testFormatsPlainRoadRunnerLine(): void
    {
        $line = '2026-07-01T13:18:40+0000        INFO    server          RoadRunner started';

        $formatted = RoadRunnerLogFormatter::format($line);

        $this->assertStringContainsString('13:18:40', $formatted);
        $this->assertStringContainsString('INFO', $formatted);
        $this->assertStringContainsString('server', $formatted);
        $this->assertStringContainsString('RoadRunner started', $formatted);
    }

    public function testLineWriterBuffersPartialChunks(): void
    {
        $lines = [];
        $writer = new RoadRunnerLogLineWriter(static function (string $line) use (&$lines): void {
            $lines[] = $line;
        });

        $writer->push('2026-07-01T13:18:42+0000        INFO    http            http log        {"status": 404, "method": "GET", "URI": "/missing", "write_bytes": 0, "elapsed": 2}');
        $this->assertSame([], $lines);

        $writer->push("\n");
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('/missing', $lines[0]);
        $this->assertStringContainsString('<comment>404</comment>', $lines[0]);
    }
}
