<?php

/**
 * >>>>>
 * --------------------------------------------------------------
 * Pionia Framework - The Restful Framework that feels restful!
 * --------------------------------------------------------------
 * >>>>>
 * This is the entry point of the application.
 *
 * It is responsible for booting the application and handling the incoming request.
 *
 * The application is bootstrapped by the application.php file in the bootstrap directory.
 *
 * The application.php file is responsible for booting the application and returning the application instance.
 *
 * >>>>>
 */
(require __DIR__ . '/../bootstrap/routes.php')
    ->bootHttp();
