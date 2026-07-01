<?php

namespace Pionia\Http;

/**
 * HTTP front controller in public/index.php — required for php -S and Apache rewrite.
 *
 * Vite builds deploy index.html and assets only; this entry must always remain.
 */
final class PublicEntryPoint
{
    public const CONTENTS = <<<'PHP'
<?php

(require __DIR__ . '/../bootstrap/routes.php')
    ->bootHttp();
PHP;

    /** @var list<string> */
    public const PRESERVE_IN_PUBLIC = ['static', '.htaccess', 'index.php'];

    public static function path(string $publicDir): string
    {
        return rtrim($publicDir, '/\\') . DIRECTORY_SEPARATOR . 'index.php';
    }

    public static function ensure(string $publicDir): void
    {
        $path = self::path($publicDir);

        if (is_file($path)) {
            return;
        }

        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0775, true);
        }

        file_put_contents($path, self::CONTENTS);
    }
}
