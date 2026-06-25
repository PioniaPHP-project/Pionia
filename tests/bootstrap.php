<?php

require __DIR__ . '/../vendor/autoload.php';

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__) . '/example');
}

if (!defined('CONTAINER_PATH')) {
    define('CONTAINER_PATH', BASE_PATH . '/bootstrap/routes.php');
}

require CONTAINER_PATH;

if (!defined('PIONIA_TESTING')) {
    define('PIONIA_TESTING', true);

    $_ENV['DEBUG'] = 'false';
    $_SERVER['DEBUG'] = 'false';
    $_ENV['APP_DEBUG'] = 'false';
    $_SERVER['APP_DEBUG'] = 'false';
    $_ENV['LOG_CHANNEL'] = 'test';
    $_SERVER['LOG_CHANNEL'] = 'test';

    $app = app();
    $nullLogger = new \Pionia\Logging\NullLogger();
    $app->set(\Psr\Log\LoggerInterface::class, $nullLogger);
    $app->get(\Pionia\Logging\LogManager::class)->extend('test', ['driver' => 'null']);
}
