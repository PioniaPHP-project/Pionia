<?php

namespace Performance;

use Pionia\Performance\FrameworkReleaseOptimizer;
use PHPUnit\Framework\TestCase;

class FrameworkReleaseOptimizerTest extends TestCase
{
    public function testOptimizeProducesFrameworkPreloadManifest(): void
    {
        $root = dirname(__DIR__, 2);
        $optimizer = new FrameworkReleaseOptimizer($root);
        $result = $optimizer->optimize();

        $this->assertTrue($result['autoload']);
        $this->assertNotNull($result['preload_files']);
        $this->assertGreaterThan(100, $result['preload_files']);
        $this->assertFileExists((string) $result['preload_path']);
    }
}
