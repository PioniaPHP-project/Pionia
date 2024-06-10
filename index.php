<?php

use Pionia\core\config\CoreKernel;
use Pionia\Logging\PioniaLogger;
use Pionia\request\Request;

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

if (!defined('SETTINGS')) {
    define('SETTINGS', __DIR__ . '/settings.ini');
}

require_once "src/bootstrap.php";

if (!defined("logger")){
    define('logger', PioniaLogger::init());
}

$kernel = new CoreKernel(new \Pionia\core\routing\BaseRoutes());

$request  = Request::createFromGlobals();
$kernel->handle($request);