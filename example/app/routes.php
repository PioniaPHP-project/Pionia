<?php

use Pionia\Core\Routing\PioniaRouter;
use Pionia\Exceptions\ControllerException;

require_once __DIR__ . '/switches/V1Switch.php';

$router  = new PioniaRouter();

try {
    $router->addSwitchFor("\app\switches\V1Switch");
} catch (ControllerException $e) {
    logger->debug($e->getMessage());
    exit();
}

return $router->getRoutes();
