<?php

use Pionia\Base\PioniaApplication;

include __DIR__ . '/../../vendor/autoload.php';

$appPath = dirname(__DIR__);

$app = new PioniaApplication($appPath);

$app->httpsOnly(false);

return $app;
