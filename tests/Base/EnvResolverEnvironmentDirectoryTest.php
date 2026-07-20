<?php

namespace Base;

use Pionia\Base\EnvResolver;
use Pionia\TestSuite\PioniaTestCase;
use ReflectionMethod;

final class EnvResolverEnvironmentDirectoryTest extends PioniaTestCase
{
    private string $tmpRoot;

    private string $cwdBefore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cwdBefore = getcwd() ?: '.';
        $this->tmpRoot = sys_get_temp_dir() . '/pionia-env-' . uniqid('', true);
        mkdir($this->tmpRoot . '/app/environment', 0777, true);
        mkdir($this->tmpRoot . '/cwd/environment', 0777, true);
        file_put_contents($this->tmpRoot . '/app/environment/.env', "APP_NAME=\"FromBasePath\"\n");
        file_put_contents($this->tmpRoot . '/cwd/environment/.env', "APP_NAME=\"FromCwd\"\n");
    }

    protected function tearDown(): void
    {
        chdir($this->cwdBefore);
        $this->removeTree($this->tmpRoot);
        parent::tearDown();
    }

    public function testPrefersBasePathEnvironmentOverCwd(): void
    {
        chdir($this->tmpRoot . '/cwd');

        $resolver = new EnvResolver('environment');
        $method = new ReflectionMethod(EnvResolver::class, 'environmentDirectory');
        $dir = $method->invoke($resolver);

        $expected = realpath(BASE_PATH . '/environment') ?: (BASE_PATH . DIRECTORY_SEPARATOR . 'environment');
        $actual = realpath($dir) ?: $dir;
        $this->assertSame($expected, $actual);
        $this->assertStringNotContainsString($this->tmpRoot . '/cwd', $dir);
    }

    public function testTitleizeHelper(): void
    {
        $this->assertSame('My Shop', titleize('my-shop'));
        $this->assertSame('Demo App', titleize('demo_app'));
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeTree($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
