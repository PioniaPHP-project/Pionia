<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class ViewRoadRunnerLogsCommandTest extends PioniaTestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pionia_rr_log_' . uniqid() . '.log';
        file_put_contents($this->logFile, "line-one\nline-two\nline-three\n");
    }

    protected function tearDown(): void
    {
        if (is_file($this->logFile)) {
            unlink($this->logFile);
        }

        parent::tearDown();
    }

    public function testPrintsLastLinesWithoutFollowing(): void
    {
        $code = $this->artisan('runserver:logs', [
            '--log' => $this->logFile,
            '--lines' => 2,
            '--no-follow' => true,
        ]);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('line-two', $this->consoleOutput());
        $this->assertStringContainsString('line-three', $this->consoleOutput());
        $this->assertStringNotContainsString('line-one', $this->consoleOutput());
    }

    public function testFailsWhenLogMissingAndNotWaiting(): void
    {
        $missing = $this->logFile . '.missing';

        $code = $this->artisan('runserver:logs', [
            '--log' => $missing,
            '--no-follow' => true,
        ]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('Log file not found', $this->consoleOutput());
    }
}
