#!/usr/bin/env php
<?php

/**
 * Initialize the example SQLite database from database/schema.sql.
 */

$exampleRoot = dirname(__DIR__);
$dbPath = $exampleRoot . '/database.sqlite3';
$schema = $exampleRoot . '/database/schema.sql';

if (!is_file($schema)) {
    fwrite(STDERR, "Schema not found: {$schema}\n");
    exit(1);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec((string) file_get_contents($schema));

echo "Initialized {$dbPath}\n";
