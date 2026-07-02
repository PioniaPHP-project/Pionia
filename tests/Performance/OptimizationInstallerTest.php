<?php

namespace Performance;

use Pionia\Performance\OptimizationInstaller;
use Pionia\Performance\PreloadManifest;
use PHPUnit\Framework\TestCase;

class OptimizationInstallerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/pionia-opt-install-' . uniqid('', true);
        mkdir($this->tempDir, 0775, true);
        mkdir($this->tempDir . '/environment', 0775, true);
        file_put_contents($this->tempDir . '/environment/settings.ini', "[server]\nPORT=8003\n");
        file_put_contents($this->tempDir . '/.gitignore', "/vendor/\n");
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testInstallCreatesOptInFiles(): void
    {
        $installer = new OptimizationInstaller();
        $installed = $installer->install($this->tempDir);

        $this->assertFileExists($this->tempDir . '/bootstrap/preload.php');
        $this->assertFileExists($this->tempDir . '/environment/php.ini.production.example');
        $this->assertFileExists($this->tempDir . '/storage/bootstrap/.gitkeep');
        $this->assertNotEmpty($installed);

        $settings = (string) file_get_contents($this->tempDir . '/environment/settings.ini');
        $this->assertStringContainsString('[performance]', $settings);

        $this->assertTrue(OptimizationInstaller::isInstalled($this->tempDir));
        $this->assertTrue(PreloadManifest::fromSettings($this->tempDir)['enabled']);
    }

    public function testInstallIsIdempotent(): void
    {
        $installer = new OptimizationInstaller();
        $first = $installer->install($this->tempDir);
        $second = $installer->install($this->tempDir);

        $this->assertNotEmpty($first);
        $this->assertSame([], $second);
    }

    public function testUninstallScaffoldRemovesOptInFiles(): void
    {
        $installer = new OptimizationInstaller();
        $installer->install($this->tempDir);
        $removed = $installer->uninstallScaffold($this->tempDir);

        $this->assertFileDoesNotExist($this->tempDir . '/bootstrap/preload.php');
        $this->assertStringNotContainsString('[performance]', (string) file_get_contents($this->tempDir . '/environment/settings.ini'));
        $this->assertNotEmpty($removed);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        foreach (scandir($directory) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}
