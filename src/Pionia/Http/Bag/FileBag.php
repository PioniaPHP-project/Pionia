<?php

namespace Pionia\Http\Bag;

use Pionia\Http\UploadedFile;

/**
 * Uploaded file parameter bag.
 */
class FileBag extends ParameterBag
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function __construct(array $parameters = [])
    {
        parent::__construct($this->normalize($parameters));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = parent::get($key, $default);

        return $file instanceof UploadedFile ? $file : $default;
    }

    /**
     * @param array<string, mixed> $files
     * @return array<string, mixed>
     */
    private function normalize(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $file) {
            if (is_array($file) && isset($file['tmp_name'])) {
                $normalized[$key] = UploadedFile::fromPhpArray($file);
            } elseif ($file instanceof UploadedFile) {
                $normalized[$key] = $file;
            }
        }

        return $normalized;
    }
}
