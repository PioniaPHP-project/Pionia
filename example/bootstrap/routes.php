<?php
require __DIR__ . '/../../vendor/autoload.php';

use Application\Switches\V1Switch;
use Pionia\Http\Routing\PioniaRouter;

$router = new PioniaRouter();

$router->wireTo(V1Switch::class);

return $router;
