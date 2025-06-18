<?php

require __DIR__ . '/../../vendor/autoload.php';

use Application\Switches\MainSwitch;


$app = require __DIR__.DIRECTORY_SEPARATOR.'application.php';

$router = route($app)
    ->switch(MainSwitch::class, 'v1');

return $app;
