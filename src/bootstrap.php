
<?php


/**
 * This is the bootstrap file for the framework
 *
 * It is the entry point for the framework and should be included in all files that need to use the framework
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */

use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Events\PioniaEventDispatcher;
use Pionia\Pionia\Logging\PioniaLogger;

$autoloader = require __DIR__ . '/../vendor/autoload.php';
$container = new DI\Container();
$eventDispatcher = new PioniaEventDispatcher();
$container->set(PioniaEventDispatcher::class, $eventDispatcher);
$app = new PioniaApplication($container);
$app->powerUp();

