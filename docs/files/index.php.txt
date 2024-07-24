<?php

use Pionia\Core\Pionia;
use Pionia\Logging\PioniaLogger;

require_once "src/bootstrap.php";

if (!defined("logger")){
    define('logger', PioniaLogger::init());
}

/**
 * @throws ErrorException
 */
function exceptions_error_handler($severity, $message, $filename, $lineno)
{
    logger->debug($message, [
        'severity' => $severity,
        'filename' => $filename,
        'lineno' => $lineno
    ]);
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}

if (!file_exists(SETTINGS)) {
    dd('Settings file not found');
}

if (!defined("pionia")){
    define('pionia', Pionia::boot());
}

set_error_handler('exceptions_error_handler');

