<?php

namespace Utils;

use Pionia\Utils\FrameworkVersion;
use PHPUnit\Framework\TestCase;

class FrameworkVersionTest extends TestCase
{
    public function testDetectReturnsNonEmptyString(): void
    {
        $version = FrameworkVersion::detect();

        $this->assertNotSame('', $version);
    }

    public function testDetectMatchesComposerWhenInstalled(): void
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            $this->markTestSkipped('Composer InstalledVersions not available.');
        }

        if (!\Composer\InstalledVersions::isInstalled(FrameworkVersion::PACKAGE)) {
            $this->markTestSkipped('pionia/pionia-core is not installed via Composer in this tree.');
        }

        $this->assertSame(
            \Composer\InstalledVersions::getPrettyVersion(FrameworkVersion::PACKAGE),
            FrameworkVersion::detect(),
        );
    }
}
