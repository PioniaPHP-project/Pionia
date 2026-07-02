<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class OptimizePreloadCommandTest extends PioniaTestCase
{
    public function testListIncludesOptimizePreloadCommand(): void
    {
        $code = $this->artisan('list');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('optimize:preload', $this->consoleOutput());
    }
}
