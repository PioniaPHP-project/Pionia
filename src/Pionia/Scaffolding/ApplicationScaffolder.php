<?php

namespace Pionia\Scaffolding;

use Pionia\Utils\Filesystem;

/**
 * Copies and token-replaces application scaffold stubs.
 */
final class ApplicationScaffolder
{
    private string $stubsPath;

    public function __construct(?string $stubsPath = null)
    {
        $this->stubsPath = $stubsPath ?? dirname(__DIR__) . '/Resources/scaffolds/app';
    }

    /**
     * @param array<string, string> $replacements
     */
    public function scaffold(string $targetDirectory, array $replacements): void
    {
        $fs = new Filesystem();

        if ($fs->exists($targetDirectory)) {
            throw new \RuntimeException("Target directory already exists: {$targetDirectory}");
        }

        if (!is_dir($this->stubsPath)) {
            throw new \RuntimeException('Application scaffold stubs not found.');
        }

        $this->copyStubs($this->stubsPath, $targetDirectory, $replacements);
    }

    /**
     * @param array<string, string> $replacements
     */
    private function copyStubs(string $source, string $destination, array $replacements): void
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0775, true);
        }

        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $entry;
            $targetName = str_replace('.stub', '', $entry);
            $targetPath = $destination . DIRECTORY_SEPARATOR . $targetName;

            if (is_dir($sourcePath)) {
                $this->copyStubs($sourcePath, $targetPath, $replacements);

                continue;
            }

            $contents = (string) file_get_contents($sourcePath);
            $contents = str_replace(array_keys($replacements), array_values($replacements), $contents);

            $parent = dirname($targetPath);
            if (!is_dir($parent)) {
                mkdir($parent, 0775, true);
            }

            file_put_contents($targetPath, $contents);

            if (str_ends_with($targetName, 'pionia')) {
                chmod($targetPath, 0755);
            }
        }
    }
}
