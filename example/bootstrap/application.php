<?php

require __DIR__ . '/../../vendor/autoload.php';

use Pionia\Pionia\Base\PioniaApplication;

$appPath = dirname(__DIR__);

$app = new PioniaApplication($appPath);

$app->httpsOnly(false);

return $app;
