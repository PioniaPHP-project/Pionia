<?php

/**
 * OPcache preload entry point.
 *
 * Point php.ini opcache.preload at this file (not storage/bootstrap/preload.php).
 * Installed by `php pionia optimize`.
 */
if (!function_exists('opcache_compile_file')) {
    return;
}

$generated = __DIR__ . '/../storage/bootstrap/preload.php';

if (is_file($generated)) {
    require $generated;
}
