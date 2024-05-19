
<?php


/**
 * This is the bootstrap file for the framework
 *
 * It is the entry point for the framework and should be included in all files that need to use the framework
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */

use Pionia\Logging\PioniaLogger;
use Pionia\validators\Validator;

set_exception_handler('exception_handler');

function exception_handler(Throwable $e): void
{
    $logger = PioniaLogger::init();
    $logger->debug($e->getMessage(), $e->getTrace());
}

$autoloader = require __DIR__ . '/../vendor/autoload.php';


$validator = new Validator();

echo $validator->asPhone('0781109107');
