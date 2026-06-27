<?php

namespace Pionia\Http;

use InvalidArgumentException;
use RuntimeException;

/**
 * Represents an uploaded file or a test/temporary file on disk.
 */
final class UploadedFile
{
    public function __construct(
        private readonly string $path,
        private readonly string $originalName,
        private readonly ?string $mimeType = null,
        private readonly ?int $error = null,
        private readonly bool $test = false,
    ) {
        if ($error !== null && $error !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(self::uploadErrorMessage($error));
        }

        if (!is_file($path)) {
            throw new InvalidArgumentException(sprintf('File "%s" does not exist.', $path));
        }
    }

    /**
     * @param array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $file
     */
    public static function fromPhpArray(array $file): self
    {
        return new self(
            (string) ($file['tmp_name'] ?? ''),
            (string) ($file['name'] ?? ''),
            $file['type'] ?? null,
            $file['error'] ?? UPLOAD_ERR_OK,
            !is_uploaded_file((string) ($file['tmp_name'] ?? '')),
        );
    }

    public function getClientOriginalName(): string
    {
        return $this->originalName;
    }

    public function getClientMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return (int) filesize($this->path);
    }

    public function getPathname(): string
    {
        return $this->path;
    }

    public function guessClientExtension(): string
    {
        $extension = pathinfo($this->originalName, PATHINFO_EXTENSION);

        if (is_string($extension) && $extension !== '') {
            return strtolower($extension);
        }

        $mime = $this->mimeType ?? mime_content_type($this->path) ?: '';

        return match (true) {
            str_contains($mime, 'jpeg') => 'jpg',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'pdf') => 'pdf',
            default => 'bin',
        };
    }

    public function move(string $directory, ?string $name = null): self
    {
        $targetName = $name ?? $this->originalName;
        $target = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $targetName;

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Unable to create directory "%s".', $directory));
        }

        if ($this->test || !is_uploaded_file($this->path)) {
            if (!rename($this->path, $target)) {
                throw new RuntimeException(sprintf('Could not move the file "%s" to "%s".', $this->path, $target));
            }
        } elseif (!move_uploaded_file($this->path, $target)) {
            throw new RuntimeException(sprintf('Could not move the file "%s" to "%s".', $this->path, $target));
        }

        return new self($target, $targetName, $this->mimeType);
    }

    private static function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The uploaded file was too large.',
            UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
            default => 'Unknown upload error.',
        };
    }
}
