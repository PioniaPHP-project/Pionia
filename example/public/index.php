<?php

use Pionia\Pionia\Http\Base\WebKernel;

define('PIONIA_START', microtime(true));

include __DIR__ . '/../../vendor/autoload.php';

if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__DIR__, 1));
}

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
 *
 * @return WebKernel $app
 */
(require __DIR__ . '/../bootstrap/application.php')
    // Register the routes
    ->withEndPoints(require __DIR__ . '/../bootstrap/routes.php')
    // Collect the context and boot the application
    // This does not boot the request kernel. It only sets up the application context.
    // From now onwards,you can access the application globally using `app()` or `app` or in your services using `$this->app`
    ->powerUp()
    // Boot the request kernel and handle the incoming request
    ->handleRequest();
