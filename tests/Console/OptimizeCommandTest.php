<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class OptimizeCommandTest extends PioniaTestCase
{
    private array $cleanupPaths = [];

    protected function tearDown(): void
    {
        foreach ($this->cleanupPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $this->cleanupPaths = [];
        parent::tearDown();
    }

    public function testOptimizeInstallsScaffoldAndGeneratesPreloadScript(): void
    {
        $preloadEntry = BASE_PATH . '/bootstrap/preload.php';
        $generated = BASE_PATH . '/storage/bootstrap/preload.php';

        foreach ([$preloadEntry, $generated] as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        $code = $this->artisan('optimize', ['--no-autoload' => true]);

        $this->assertSame(0, $code);
        $this->assertFileExists($preloadEntry);
        $this->assertFileExists($generated);
        $this->assertStringContainsString('Installed production optimization files', $this->consoleOutput());
        $this->assertStringContainsString('opcache_compile_file', (string) file_get_contents($generated));

        $this->cleanupPaths[] = $generated;
    }

    public function testOptimizeClearRemovesArtifacts(): void
    {
        $preload = BASE_PATH . '/storage/bootstrap/preload.php';
        if (!is_dir(dirname($preload))) {
            mkdir(dirname($preload), 0775, true);
        }

        file_put_contents($preload, "<?php\n");

        $code = $this->artisan('optimize:clear');

        $this->assertSame(0, $code);
        $this->assertFileDoesNotExist($preload);
    }

    public function testListIncludesOptimizeCommands(): void
    {
        $code = $this->artisan('list');

        $this->assertSame(0, $code);
        $output = $this->consoleOutput();
        $this->assertStringContainsString('optimize', $output);
        $this->assertStringContainsString('optimize:clear', $output);
    }
}
