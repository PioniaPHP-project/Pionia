<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class ListAliasCommandTest extends PioniaTestCase
{
    public function testAppAliasesCommandListsBuiltInAliases(): void
    {
        $code = $this->artisan('app:aliases');

        $this->assertSame(0, $code);
        $output = $this->consoleOutput();
        $this->assertStringContainsString('AVAILABLE ALIASES', $output);
        $this->assertStringContainsString('LOGS_DIR', $output);
    }
}
