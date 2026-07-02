<?php

namespace Pionia\Utils;

/**
 * Resolves the installed pionia-core version from Composer metadata.
 */
final class FrameworkVersion
{
    public const PACKAGE = 'pionia/pionia-core';

    public static function detect(): string
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return 'dev';
        }

        try {
            if (\Composer\InstalledVersions::isInstalled(self::PACKAGE)) {
                $version = \Composer\InstalledVersions::getPrettyVersion(self::PACKAGE);

                return $version !== '' ? $version : 'dev';
            }

            $root = \Composer\InstalledVersions::getRootPackage();
            if (($root['name'] ?? '') === self::PACKAGE) {
                return (string) ($root['pretty_version'] ?? 'dev');
            }
        } catch (\Throwable) {
        }

        return 'dev';
    }
}
