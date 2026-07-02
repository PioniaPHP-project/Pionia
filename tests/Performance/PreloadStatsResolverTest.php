<?php

namespace Performance;

use Pionia\Performance\PreloadStatsResolver;
use PHPUnit\Framework\TestCase;

class PreloadStatsResolverTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/pionia-stats-' . uniqid('', true);
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testWriteAndReadSnapshot(): void
    {
        if (!function_exists('opcache_get_status')) {
            $this->markTestSkipped('OPcache extension not available.');
        }

        $service = $this->tempDir . '/services/DemoService.php';
        mkdir(dirname($service), 0775, true);
        file_put_contents($service, '<?php namespace Application\\Services; class DemoService {}');

        try {
            @opcache_compile_file($service);
        } catch (\Throwable) {
            $this->markTestSkipped('OPcache cannot compile files in this PHP SAPI.');
        }

        $written = PreloadStatsResolver::writeSnapshot($this->tempDir);
        if ($written === null) {
            $this->markTestSkipped('OPcache scripts unavailable in CLI.');
        }

        $this->assertFileExists($written['path']);
        $hot = PreloadStatsResolver::resolveHotScripts($this->tempDir);
        $this->assertNotEmpty($hot);
    }

    public function testFilterRelevantSkipsUnrelatedPaths(): void
    {
        $appRoot = $this->tempDir;
        $service = $appRoot . '/services/Foo.php';
        mkdir(dirname($service), 0775, true);
        file_put_contents($service, '<?php class Foo {}');

        $scripts = [
            $service,
            '/tmp/unrelated.php',
            $appRoot . '/vendor/bin/rr',
        ];

        $filtered = PreloadStatsResolver::filterRelevant($scripts, $appRoot);

        $this->assertSame([$service], $filtered);
    }

    public function testReadSnapshotFromJsonFile(): void
    {
        $service = $this->tempDir . '/services/Bar.php';
        mkdir(dirname($service), 0775, true);
        file_put_contents($service, '<?php class Bar {}');

        $snapshotPath = PreloadStatsResolver::snapshotPath($this->tempDir);
        mkdir(dirname($snapshotPath), 0775, true);
        file_put_contents($snapshotPath, json_encode([
            'recorded_at' => gmdate('c'),
            'scripts' => [['path' => $service]],
        ], JSON_THROW_ON_ERROR));

        $hot = PreloadStatsResolver::resolveHotScripts($this->tempDir);
        $this->assertSame([$service], $hot);
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
