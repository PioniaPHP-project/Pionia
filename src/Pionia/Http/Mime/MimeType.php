<?php

namespace Pionia\Http\Mime;

/**
 * Lightweight MIME detection without symfony/mime.
 */
final class MimeType
{
    private const array MAP = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'html' => 'text/html',
        'htm' => 'text/html',
        'txt' => 'text/plain',
        'xml' => 'application/xml',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4',
    ];

    public static function guess(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION) ?: '');

        if (isset(self::MAP[$extension])) {
            return self::MAP[$extension];
        }

        if (is_file($path) && function_exists('mime_content_type')) {
            $detected = @mime_content_type($path);
            if (is_string($detected) && $detected !== '') {
                return $detected;
            }
        }

        return 'application/octet-stream';
    }
}
