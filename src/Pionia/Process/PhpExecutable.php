<?php

namespace Pionia\Process;

/**
 * Locate the PHP CLI binary (replaces Symfony PhpExecutableFinder).
 */
final class PhpExecutable
{
    public static function find(bool $includeArgs = false): string
    {
        $binary = self::binary();

        if (!$includeArgs) {
            return $binary;
        }

        $args = array_slice($_SERVER['argv'] ?? [], 1);

        return $args === [] ? $binary : $binary . ' ' . implode(' ', array_map('escapeshellarg', $args));
    }

    public static function binary(): string
    {
        if (defined('PHP_BINARY') && is_string(PHP_BINARY) && PHP_BINARY !== '') {
            return PHP_BINARY;
        }

        if (defined('PHP_BINDIR') && is_string(PHP_BINDIR) && PHP_BINDIR !== '') {
            $candidate = PHP_BINDIR . DIRECTORY_SEPARATOR . 'php';
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return 'php';
    }
}
