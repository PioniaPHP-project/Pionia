<?php

namespace Utils;

use Pionia\Utils\SafePath;
use Pionia\TestSuite\PioniaTestCase;

class SafePathTest extends PioniaTestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseDir = sys_get_temp_dir() . '/pionia-safepath-' . uniqid('', true);
        mkdir($this->baseDir, 0775, true);
        file_put_contents($this->baseDir . '/allowed.txt', 'ok');
    }

    protected function tearDown(): void
    {
        if (is_file($this->baseDir . '/allowed.txt')) {
            unlink($this->baseDir . '/allowed.txt');
        }
        if (is_dir($this->baseDir)) {
            rmdir($this->baseDir);
        }
        parent::tearDown();
    }

    public function testResolvesFileWithinBase(): void
    {
        $resolved = SafePath::resolveFileWithinBase($this->baseDir, 'allowed.txt');
        $this->assertSame(realpath($this->baseDir . '/allowed.txt'), $resolved);
    }

    public function testRejectsParentTraversal(): void
    {
        $this->assertNull(SafePath::resolveFileWithinBase($this->baseDir, '../allowed.txt'));
        $this->assertNull(SafePath::resolveFileWithinBase($this->baseDir, 'foo/../../etc/passwd'));
    }

    public function testRejectsMissingFile(): void
    {
        $this->assertNull(SafePath::resolveFileWithinBase($this->baseDir, 'missing.txt'));
    }
}
