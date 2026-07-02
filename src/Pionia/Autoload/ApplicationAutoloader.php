<?php

namespace Pionia\Autoload;

/**
 * Maps Application\* classes to lowercase app directories (services/, switches/, …).
 *
 * Register once from bootstrap/application.php so composer.json stays minimal.
 */
final class ApplicationAutoloader
{
    public static function register(string $appRoot): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;
        $appRoot = rtrim($appRoot, DIRECTORY_SEPARATOR . '/\\');

        spl_autoload_register(
            static function (string $class) use ($appRoot): void {
                $path = self::resolvePath($appRoot, $class);

                if ($path !== null) {
                    require $path;
                }
            },
            true,
            true,
        );
    }

    public static function resolvePath(string $appRoot, string $class): ?string
    {
        if (!str_starts_with($class, 'Application\\')) {
            return null;
        }

        $relative = substr($class, strlen('Application\\'));

        if ($relative === false || $relative === '') {
            return null;
        }

        $appRoot = rtrim($appRoot, DIRECTORY_SEPARATOR . '/\\');
        $parts = explode('\\', $relative);
        $className = (string) array_pop($parts);
        $directory = strtolower(implode(DIRECTORY_SEPARATOR, $parts));
        $path = $appRoot . DIRECTORY_SEPARATOR
            . ($directory !== '' ? $directory . DIRECTORY_SEPARATOR : '')
            . $className
            . '.php';

        return is_file($path) ? $path : null;
    }
}
