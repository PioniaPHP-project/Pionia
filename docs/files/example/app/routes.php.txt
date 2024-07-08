<?php

use Pionia\Core\Routing\PioniaRouter;
use Pionia\Exceptions\ControllerException;

require_once __DIR__ . '/switches/V1Switch.php';

$router  = new PioniaRouter();

try {
    $api = '\app\switches\V1Switch';
    $router->addSwitchFor($api);
} catch (ControllerException $e) {
    echo $e->getMessage();
    die();
}

return $router->getRoutes();
