<?php
require __DIR__ . '/../../vendor/autoload.php';

use Application\Switches\V1Switch;
use Pionia\Pionia\Http\Routing\PioniaRouter;

$router = new PioniaRouter();

$router->addSwitchFor(V1Switch::class);

return $router;
