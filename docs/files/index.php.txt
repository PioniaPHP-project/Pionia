<?php

use Pionia\Logging\PioniaLogger;
use Pionia\Response\BaseResponse;

require_once "src/bootstrap.php";

if (!defined("logger")){
    define('logger', PioniaLogger::init());
}

function exceptions_error_handler($severity, $message, $filename, $lineno)
{
    logger->debug($message, [
        'severity' => $severity,
        'filename' => $filename,
        'lineno' => $lineno
    ]);
    throw new ErrorException($message, 0, $severity, $filename, $lineno);
}
set_error_handler('exceptions_error_handler');

