<?php

require BASEPATH . '/../vendor/autoload.php';

use Application\Switches\MainSwitch;
use Pionia\Http\Routing\PioniaRouter;

/**
 * >>>>>
 * ---------------------------------------------------------------
 * Application Routes(bootstrap/routes.php)
 * ---------------------------------------------------------------
 * >>>>>
 * This is where you register the routes for your application.
 * Routes in Pionia are like electricity wires to switches.
 *
 * Each route is associated with a switch that handles the request.
 *
 * Each route represents a unique API version.
 *
 * You can add new routes by using the `->wireTo()` method.
 * The v1 `addSwitchFor` is not an acronym for this `wireTo` method.
 * >>>>>
 */
$router = (new PioniaRouter())
    ->wireTo(MainSwitch::class);


/**
 * >>>>>
 * ---------------------------------------------------------------
 * Return the router
 * ---------------------------------------------------------------
 * >>>>>
 * This must stay as the last line of the file.
 * It returns the router to the application.
 * >>>>>
 */
return $router;
