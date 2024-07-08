<?php

//require __DIR__ . '/../vendor/autoload.php';
//
//if (!defined('BASEPATH')) {
//    define('BASEPATH', __DIR__);
//}
//
//if (!defined('SETTINGS')) {
//    define('SETTINGS', __DIR__ . '/settings.ini');
//}
//
//require_once __DIR__."/../src/bootstrap.php";
//
//use Pionia\core\config\CoreKernel;
//
//$routes = require __DIR__ . '/app/routes.php';
//
//$kernel = new CoreKernel($routes);
//
//$response = $kernel->run();

use Pionia\Core\Config\CoreKernel;

if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__);
}

// set our settings globally
if (!defined('SETTINGS')) {
    define('SETTINGS', BASEPATH . '/settings.ini');
}

require_once __DIR__ . '/../index.php';

$routes = require_once BASEPATH . '/app/routes.php';

$kernel = new CoreKernel($routes);

$response = $kernel->run();

