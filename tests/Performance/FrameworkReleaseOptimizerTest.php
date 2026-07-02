<?php

namespace Performance;

use Pionia\Performance\FrameworkReleaseOptimizer;
use PHPUnit\Framework\TestCase;

class FrameworkReleaseOptimizerTest extends TestCase
{
    public function testOptimizeProducesPortableFrameworkPreloadInPackage(): void
    {
        $root = dirname(__DIR__, 2);
        $optimizer = new FrameworkReleaseOptimizer($root);
        $result = $optimizer->optimize();

        $this->assertTrue($result['autoload']);
        $this->assertNotNull($result['preload_files']);
        $this->assertGreaterThan(100, $result['preload_files']);

        $path = (string) $result['preload_path'];
        $this->assertFileExists($path);
        $this->assertStringEndsWith('framework-preload.php', $path);

        $contents = (string) file_get_contents($path);
        $this->assertStringContainsString('dirname(__DIR__, 2)', $contents);
        $this->assertStringNotContainsString($root, $contents);
        $this->assertStringContainsString('Http/', $contents);

        @unlink($path);
    }
}
