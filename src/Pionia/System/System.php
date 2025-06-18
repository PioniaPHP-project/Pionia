<?php

namespace Pionia\System;



class System implements MimeTypeDetectorInterface
{
    /**
     * @var array<string,string>  Map: extension (without dot) → MIME type
     */
    private array $mimeMap = [
        // Images
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'png'   => 'image/png',
        'gif'   => 'image/gif',
        'bmp'   => 'image/bmp',
        'webp'  => 'image/webp',
        'svg'   => 'image/svg+xml',
        'ico'   => 'image/x-icon',

        // Documents
        'pdf'   => 'application/pdf',
        'txt'   => 'text/plain',
        'csv'   => 'text/csv',
        'rtf'   => 'application/rtf',
        'doc'   => 'application/msword',
        'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls'   => 'application/vnd.ms-excel',
        'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt'   => 'application/vnd.ms-powerpoint',
        'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',

        // Archives
        'zip'   => 'application/zip',
        'gz'    => 'application/gzip',
        'tar'   => 'application/x-tar',
        'rar'   => 'application/vnd.rar',
        '7z'    => 'application/x-7z-compressed',
        'bz2'   => 'application/x-bzip2',

        // Audio/Video
        'mp3'   => 'audio/mpeg',
        'wav'   => 'audio/wav',
        'ogg'   => 'audio/ogg',
        'mp4'   => 'video/mp4',
        'mkv'   => 'video/x-matroska',
        'avi'   => 'video/x-msvideo',
        'mov'   => 'video/quicktime',
        'webm'  => 'video/webm',

        // Fonts
        'ttf'   => 'font/ttf',
        'otf'   => 'font/otf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',

        // JSON/XML/YAML
        'json'  => 'application/json',
        'xml'   => 'application/xml',
        'yml'   => 'application/x-yaml',
        'yaml'  => 'application/x-yaml',

        // Code / Scripts
        'js'    => 'application/javascript',
        'css'   => 'text/css',
        'html'  => 'text/html',
        'htm'   => 'text/html',
        'php'   => 'text/x-php',

        // Default “binary” fallback if needed
        'bin'   => 'application/octet-stream',
    ];

    /**
     * @inheritDoc
     */
    public static function detect(string $filePath): ?string
    {
        $instance = new self();
        // 1) File must exist and be readable
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return null;
        }

        // 2) Extract extension (the part after the last dot)
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        if ($extension === '') {
            return null;
        }

        // 3) Special case: double extensions (e.g. ".tar.gz", ".tar.bz2")
        //    If the string ends with “tar.gz” or “tar.bz2”, override $extension.
        $lower = strtolower($filePath);
        if (str_ends_with($lower, '.tar.gz')) {
            $extension = 'tar.gz';
        } elseif (str_ends_with($lower, '.tar.bz2')) {
            $extension = 'tar.bz2';
        }

        // 4) Lookup in the internal map
        if (array_key_exists($extension, $instance->mimeMap)) {
            return $instance->mimeMap[$extension];
        }

        // 5) If double‐extension map exists (you can add more if needed)
        if ($extension === 'tar.gz') {
            return 'application/gzip';
        }
        if ($extension === 'tar.bz2') {
            return 'application/x-bzip2';
        }

        // 6) Unknown extension
        return null;
    }
}
