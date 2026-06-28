<?php

/**
 * Set APP_NAME in .env from the project directory name (post-create-project).
 */
$appDir = dirname(__DIR__, 2);
$file = $appDir . DIRECTORY_SEPARATOR . 'environment' . DIRECTORY_SEPARATOR . '.env';

if (!is_file($file)) {
    return;
}

$lines = file($file, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    return;
}

$name = basename($appDir);
$out = [];

foreach ($lines as $line) {
    if (str_starts_with(trim($line), 'APP_NAME=')) {
        $out[] = 'APP_NAME="' . addslashes($name) . '"';
    } else {
        $out[] = $line;
    }
}

file_put_contents($file, implode(PHP_EOL, $out) . PHP_EOL);
