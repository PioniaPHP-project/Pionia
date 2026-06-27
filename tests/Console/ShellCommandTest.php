<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class ShellCommandTest extends PioniaTestCase
{
    public function testShellFailsWithoutInteractiveTerminal(): void
    {
        $code = $this->artisan('shell', ['--no-interaction' => true]);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('interactive terminal', $this->consoleOutput());
    }

    public function testShellAppearsInListCommand(): void
    {
        $this->artisan('list');

        $this->assertStringContainsString('shell', $this->consoleOutput());
    }
}
