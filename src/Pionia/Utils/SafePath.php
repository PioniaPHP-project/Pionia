<?php

namespace Pionia\Utils;

/**
 * Filesystem path resolution with directory traversal protection.
 */
final class SafePath
{
    /**
     * Resolve a relative path to a file that must stay within $baseDir.
     */
    public static function resolveFileWithinBase(string $baseDir, string $relativePath): ?string
    {
        $normalized = str_replace(['\\', "\0"], ['/', ''], $relativePath);
        if (str_contains($normalized, '..')) {
            return null;
        }

        $base = str_starts_with($baseDir, DIRECTORY_SEPARATOR)
            ? $baseDir
            : path($baseDir);
        $baseReal = realpath($base);
        if ($baseReal === false) {
            return null;
        }

        $candidate = $baseReal . DIRECTORY_SEPARATOR . ltrim($normalized, '/');
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved)) {
            return null;
        }

        $basePrefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($resolved, $basePrefix) && $resolved !== $baseReal) {
            return null;
        }

        return $resolved;
    }
}
