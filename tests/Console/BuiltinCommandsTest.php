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
    }
}
