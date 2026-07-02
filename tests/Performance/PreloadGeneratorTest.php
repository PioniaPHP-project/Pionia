<?php

namespace Performance;

use Pionia\Http\Routing\RouteTable;
use Pionia\Performance\BootstrapCacheGenerator;
use Pionia\Performance\PreloadGenerator;
use Pionia\Performance\PreloadManifest;
use PHPUnit\Framework\TestCase;

class PreloadGeneratorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/pionia-preload-' . uniqid('', true);
        mkdir($this->tempDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testGeneratorWritesOpcacheCompileCalls(): void
    {
        $frameworkDir = $this->tempDir . '/vendor/pionia/pionia-core/src/Pionia/Demo';
        mkdir($frameworkDir, 0775, true);
        file_put_contents($frameworkDir . '/Sample.php', '<?php namespace Pionia\\Demo; class Sample {}');

        $servicesDir = $this->tempDir . '/services';
        mkdir($servicesDir, 0775, true);
        file_put_contents($servicesDir . '/WelcomeService.php', '<?php namespace Application\\Services; class WelcomeService {}');

        $generator = new PreloadGenerator($this->tempDir);
        $output = $this->tempDir . '/storage/bootstrap/preload.php';
        $result = $generator->generate($output);

        $this->assertSame($output, $result['path']);
        $this->assertGreaterThanOrEqual(2, $result['files']);
        $this->assertFileExists($output);

        $contents = (string) file_get_contents($output);
        $this->assertStringContainsString('opcache_compile_file', $contents);
        $this->assertStringContainsString('Sample.php', $contents);
        $this->assertStringContainsString('WelcomeService.php', $contents);
    }

    public function testManifestExcludesGeneratorPaths(): void
    {
        $generatorPath = $this->tempDir . '/vendor/pionia/pionia-core/src/Pionia/Builtins/Commands/Generators/Foo.php';
        mkdir(dirname($generatorPath), 0775, true);
        file_put_contents($generatorPath, '<?php class Foo {}');

        $allowedPath = $this->tempDir . '/vendor/pionia/pionia-core/src/Pionia/Http/Kernel.php';
        mkdir(dirname($allowedPath), 0775, true);
        file_put_contents($allowedPath, '<?php class Kernel {}');

        $generator = new PreloadGenerator($this->tempDir);
        $output = $this->tempDir . '/preload.php';
        $generator->generate($output);

        $contents = (string) file_get_contents($output);
        $this->assertStringNotContainsString('Generators/Foo.php', $contents);
        $this->assertStringContainsString('Http/Kernel.php', $contents);
    }

    public function testBootstrapCacheRoundTrip(): void
    {
        $bootstrapDir = $this->tempDir . '/storage/bootstrap';
        mkdir($bootstrapDir, 0775, true);

        $path = $bootstrapDir . '/routes.php';
        file_put_contents($path, "<?php\n\nreturn " . var_export([
            'ping' => [
                'path' => '/api/v1/ping',
                'defaults' => ['_controller' => 'ping'],
                'requirements' => [],
                'methods' => ['GET'],
            ],
        ], true) . ";\n");

        $iniDir = $this->tempDir . '/environment';
        mkdir($iniDir, 0775, true);
        file_put_contents($iniDir . '/settings.ini', "[performance]\nBOOTSTRAP_CACHE=true\n");

        $loaded = BootstrapCacheGenerator::loadRoutes($this->tempDir);
        $this->assertInstanceOf(RouteTable::class, $loaded);
        $this->assertSame(1, count($loaded));
        $this->assertNotNull($loaded->get('ping'));
    }

    public function testManifestReadsPerformanceSection(): void
    {
        $iniDir = $this->tempDir . '/environment';
        mkdir($iniDir, 0775, true);
        file_put_contents($iniDir . '/settings.ini', <<<INI
[performance]
PRELOAD_ENABLED=false
BOOTSTRAP_CACHE=true
PRELOAD_PATHS=custom
PRELOAD_EXCLUDE=Foo
INI);

        $settings = PreloadManifest::fromSettings($this->tempDir);

        $this->assertFalse($settings['enabled']);
        $this->assertTrue($settings['bootstrap_cache']);
        $this->assertSame(['custom'], $settings['paths']);
        $this->assertSame(['Foo'], $settings['exclude']);
    }

    public function testManifestDisabledWhenNotOptedIn(): void
    {
        $settings = PreloadManifest::fromSettings($this->tempDir);
        $this->assertFalse($settings['enabled']);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
