<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class BuiltinCommandsTest extends PioniaTestCase
{
    public function testListCommandExitsSuccessfully(): void
    {
        $code = $this->artisan('list');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('serve', $this->consoleOutput());
        $this->assertStringContainsString('runserver', $this->consoleOutput());
        $this->assertStringContainsString('rr:setup', $this->consoleOutput());
        $this->assertStringContainsString('runserver:logs', $this->consoleOutput());
        $this->assertStringContainsString('stopserver', $this->consoleOutput());
        $this->assertStringContainsString('stats:view', $this->consoleOutput());
        $this->assertStringContainsString('api:docs', $this->consoleOutput());
        $this->assertStringContainsString('api:catalog', $this->consoleOutput());
    }

    public function testServeRoadRunnerFailsWithHelpfulMessageWhenBinaryMissing(): void
    {
        $rrBinary = BASE_PATH . '/rr';
        $backup = $rrBinary . '.test-bak';
        $hidden = false;

        if (is_file($rrBinary)) {
            rename($rrBinary, $backup);
            $hidden = true;
        }

        try {
            $code = $this->artisan('runserver');

            $this->assertSame(1, $code);
            $this->assertStringContainsString('RoadRunner binary (rr) not found', $this->consoleOutput());
            $this->assertStringContainsString('php pionia rr:setup', $this->consoleOutput());
        } finally {
            if ($hidden && is_file($backup)) {
                rename($backup, $rrBinary);
            }
        }
    }
}
