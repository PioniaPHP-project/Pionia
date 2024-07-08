
<?php


/**
 * This is the bootstrap file for the framework
 *
 * It is the entry point for the framework and should be included in all files that need to use the framework
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */

use Pionia\Core\Pionia;
use Pionia\Logging\PioniaLogger;

set_exception_handler('exception_handler');

function exception_handler(Throwable $e): void
{
    $logger = PioniaLogger::init();
    $logger->debug($e->getMessage(), $e->getTrace());
}

$autoloader = require __DIR__ . '/../vendor/autoload.php';

if (!defined("logger")){
    define('logger', PioniaLogger::init());
}

$settings = Pionia::resolveSettingsFromIni();

$settings = Pionia::getServerSettings();

if ($settings && array_key_exists('DEBUG', $settings) && $settings['DEBUG']){
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

