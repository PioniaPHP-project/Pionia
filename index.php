<?php

use Pionia\Logging\PioniaLogger;
use Pionia\Request\Request;

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
