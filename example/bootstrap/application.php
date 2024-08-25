<?php

require __DIR__ . '/../../vendor/autoload.php';

use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Middlewares\MiddlewareChain;

$app = new PioniaApplication();

$app
    ->booted(function ($instance){
        // Do something after the application is booted, you have the current state of the app
        }
    )->booting(function (){
        // Do something before the application is booted, you have the current state of the app
        }
    )->withMiddlewares(function (PioniaApplication $app) {
        return new MiddlewareChain($app);
    });

return $app;
