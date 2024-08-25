<?php

use Pionia\Pionia\Builtins\Commands\StartServer;

include __DIR__.'/../Utils/helpers.php';
// register here all builtins. These can be commands, Middleware, Authentications, etc.
if (!function_exists('allBuiltins')) {

    function allBuiltins(): Pionia\Pionia\Utils\Arrayable
    {
        return arr([
            'commands' => [
                'serve' => StartServer::class,
            ],
            'authentications' => [

            ],
            'middlewares' => [

            ],
        ]);
    }
}

