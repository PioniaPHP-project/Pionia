<?php

namespace Pionia\TestSuite;

/**
 * Ensures Packagist/GitHub release archives exclude dev-only paths.
 */
final class ReleaseArchiveTest extends PioniaTestCase
{
    public function test_composer_archive_excludes_dev_paths(): void
    {
        $root = dirname(__DIR__, 2);
        $cmd = 'bash ' . escapeshellarg($root . '/bin/verify-release-archive');
        exec($cmd . ' 2>&1', $output, $code);

        $this->assertSame(
            0,
            $code,
            "Release archive verification failed:\n" . implode("\n", $output),
        );
    }
}
