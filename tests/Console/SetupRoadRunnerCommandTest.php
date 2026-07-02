<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class SetupRoadRunnerCommandTest extends PioniaTestCase
{
    public function testSetupSucceedsWhenBinaryAlreadyInstalled(): void
    {
        $rrBinary = BASE_PATH . '/rr';
        if (!is_file($rrBinary)) {
            $this->markTestSkipped('RoadRunner binary not installed in example app.');
        }

        $code = $this->artisan('rr:setup');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('already installed', strtolower($this->consoleOutput()));
    }
}
