<?php

use Pionia\Pionia\Builtins\Commands\Context\ListAliasCommand;
use Pionia\Pionia\Builtins\Commands\Generators\GenerateAuthenticationBackend;
use Pionia\Pionia\Builtins\Commands\StartServer;

include __DIR__.'/../Utils/helpers.php';

// register here all builtins. These can be commands, Middleware, Authentications, etc.
if (!function_exists('allBuiltins')) {

    function allBuiltins(): Pionia\Pionia\Utils\Arrayable
    {
        return arr([
            'commands' => [
                'serve' => StartServer::class,
                'aliases' => ListAliasCommand::class,
                'generate_auth' => GenerateAuthenticationBackend::class
            ],
            'authentications' => [
            ],
            'middlewares' => [
            ],
            // all these directories are relative to the root directory of the application
            'directories' => [
                'BASEPATH' => '',
                'authentication_dir' => 'authentications',
                'middleware_dir' => 'middlewares',
                'commands_dir' => 'commands',
                'services_dir' => 'services',
                'switches_dir' => 'switches',
                'frontend_dir' => 'public/static',
                'environment_dir' => 'environment',
                'bootstrap_dir' => 'bootstrap',
                'vendor_dir' => 'vendor',
            ],
            'aliases' => [
            ]
        ]);
    }
}

