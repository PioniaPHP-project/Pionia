<?php

namespace Http;

use Pionia\Http\PublicEntryPoint;
use Pionia\TestSuite\PioniaTestCase;

class PublicEntryPointTest extends PioniaTestCase
{
    public function testEnsureCreatesIndexPhpWhenMissing(): void
    {
        $public = sys_get_temp_dir() . '/pionia-public-' . uniqid('', true);
        mkdir($public, 0775, true);
        file_put_contents($public . '/index.html', '<html></html>');

        try {
            PublicEntryPoint::ensure($public);

            $this->assertFileExists(PublicEntryPoint::path($public));
            $this->assertStringContainsString('bootHttp', (string) file_get_contents(PublicEntryPoint::path($public)));
        } finally {
            $this->removeTree($public);
        }
    }

    public function testEnsureDoesNotOverwriteExistingIndexPhp(): void
    {
        $public = sys_get_temp_dir() . '/pionia-public-' . uniqid('', true);
        mkdir($public, 0775, true);
        file_put_contents(PublicEntryPoint::path($public), '<?php // custom');

        try {
            PublicEntryPoint::ensure($public);
            $this->assertSame('<?php // custom', file_get_contents(PublicEntryPoint::path($public)));
        } finally {
            $this->removeTree($public);
        }
    }

    public function testPreserveListIncludesIndexPhp(): void
    {
        $this->assertContains('index.php', PublicEntryPoint::PRESERVE_IN_PUBLIC);
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
