<?php

namespace Pionia\Utils;

use RuntimeException;

/**
 * Small filesystem helper used by generators, cache, and env resolution.
 */
final class Filesystem
{
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    public function touch(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            $this->mkdir($directory);
        }

        if (file_exists($path)) {
            return;
        }

        if (@touch($path) === false) {
            throw new RuntimeException(sprintf('Unable to create file "%s".', $path));
        }
    }

    public function mkdir(string $path, int $mode = 0777): void
    {
        if (is_dir($path)) {
            return;
        }

        if (!@mkdir($path, $mode, true) && !is_dir($path)) {
            throw new RuntimeException(sprintf('Unable to create directory "%s".', $path));
        }
    }

    public function dumpFile(string $path, string $content): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            $this->mkdir($directory);
        }

        if (file_put_contents($path, $content) === false) {
            throw new RuntimeException(sprintf('Unable to write file "%s".', $path));
        }
    }

    public function remove(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        if (is_file($path) || is_link($path)) {
            unlink($path);

            return;
        }

        $items = scandir($path);
        if ($items === false) {
            throw new RuntimeException(sprintf('Unable to read directory "%s".', $path));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $this->remove($path . DIRECTORY_SEPARATOR . $item);
        }

        if (!@rmdir($path)) {
            throw new RuntimeException(sprintf('Unable to remove directory "%s".', $path));
        }
    }
}
