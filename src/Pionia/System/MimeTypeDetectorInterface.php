<?php

namespace Pionia\System;

interface MimeTypeDetectorInterface
{

    /**
     * Detects the MIME type of a given file.
     *
     * @param string $filePath
     * @return string|null
     */
    public static function detect(string $filePath): ?string;

}
