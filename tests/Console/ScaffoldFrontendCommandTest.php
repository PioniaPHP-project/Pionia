<?php

namespace Console;

use Pionia\Builtins\Commands\Frontend\ScaffoldFrontendCommand;
use Pionia\TestSuite\PioniaTestCase;
use ReflectionMethod;

class ScaffoldFrontendCommandTest extends PioniaTestCase
{
    public function testWriteApiHelperCreatesLibDirectory(): void
    {
        $root = sys_get_temp_dir() . '/pionia-scaffold-' . uniqid('', true);
        mkdir($root . '/src', 0777, true);

        try {
            $command = new ScaffoldFrontendCommand();
            $method = new ReflectionMethod(ScaffoldFrontendCommand::class, 'writeApiHelper');
            $method->invoke($command, $root);

            $this->assertFileExists($root . '/src/lib/pionia-api.js');
            $this->assertStringContainsString('callAction', (string) file_get_contents($root . '/src/lib/pionia-api.js'));
        } finally {
            $this->removeTree($root);
        }
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path . '/' . $entry;
            is_dir($full) ? $this->removeTree($full) : unlink($full);
        }

        rmdir($path);
    }
}
