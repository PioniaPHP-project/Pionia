<?php

define('PIONIA_START', microtime(true));

include __DIR__ . '/../../vendor/autoload.php';

/**
 * Boot the application.
 *
 * THIS IS THE ENTRY POINT OF THE APPLICATION
 *
 * It also registers the routes and sets up the application context.
 *
 * The application context is the global state of the application. It is used to store global variables and objects that are used throughout the application.
 *
 * In your services, you can access the application context using the `pionia` constant or by calling `this->app` in your service class.
 */
(require __DIR__ . '/../bootstrap/application.php')
    ->withEndPoints(require __DIR__ . '/../bootstrap/routes.php')
    ->powerUp()
    ->handleRequest();
