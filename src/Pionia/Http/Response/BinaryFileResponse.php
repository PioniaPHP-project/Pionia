<?php

namespace Pionia\Http\Response;

use Pionia\Http\Request\Request;
use SplFileInfo;

/**
 * Serves a file from disk as an HTTP response.
 */
class BinaryFileResponse extends Response
{
    public const DISPOSITION_INLINE = 'inline';

    public const DISPOSITION_ATTACHMENT = 'attachment';

    private readonly SplFileInfo $file;

    public function __construct(
        string|SplFileInfo $file,
        int $status = 200,
        array $headers = [],
        private string $disposition = self::DISPOSITION_INLINE,
        private ?string $dispositionFileName = null,
    ) {
        $this->file = $file instanceof SplFileInfo ? $file : new SplFileInfo($file);
        parent::__construct('', $status, $headers);
    }

    public function getFile(): SplFileInfo
    {
        return $this->file;
    }

    public function setContentDisposition(string $disposition, ?string $filename = null): static
    {
        $this->disposition = $disposition;
        $this->dispositionFileName = $filename ?? $this->file->getFilename();

        $this->headers->set(
            'Content-Disposition',
            sprintf('%s; filename="%s"', $disposition, addslashes((string) $this->dispositionFileName)),
        );

        return $this;
    }

    public function getContent(): string
    {
        $contents = file_get_contents($this->file->getPathname());

        return $contents === false ? '' : $contents;
    }

    public function prepare(Request $request): static
    {
        if (!$this->headers->has('Content-Length')) {
            $this->headers->set('Content-Length', (string) $this->file->getSize());
        }

        if ($this->dispositionFileName !== null && !$this->headers->has('Content-Disposition')) {
            $this->setContentDisposition($this->disposition, $this->dispositionFileName);
        }

        return $this;
    }

    public function send(): void
    {
        http_response_code($this->getStatusCode());

        foreach ($this->headers->all() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        readfile($this->file->getPathname());
    }
}
