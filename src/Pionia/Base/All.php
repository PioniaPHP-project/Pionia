<?php

use Pionia\Pionia\Builtins\Commands\Context\ListAliasCommand;
use Pionia\Pionia\Builtins\Commands\Generators\GenerateAuthenticationBackend;
use Pionia\Pionia\Builtins\Commands\Generators\GenerateService;
use Pionia\Pionia\Builtins\Commands\Generators\GenerateSwitch;
use Pionia\Pionia\Builtins\Commands\StartServer;

include __DIR__.'/../Utils/helpers.php';

enum NAMESPACES {
    case AUTHENTICATION_NS;
    case MIDDLEWARE_NS;
    case SERVICE_NS;
    case COMMAND_NS;
    case SWITCH_NS;
    case MAIL_NS;
}

enum DIRECTORIES {
    case BASEPATH;
    case AUTHENTICATION_DIR;
    case MIDDLEWARE_DIR;
    case COMMANDS_DIR;
    case SERVICES_DIR;
    case SWITCHES_DIR;
    case FRONTEND_DIR;
    case ENVIRONMENT_DIR;
    case BOOTSTRAP_DIR;
    case VENDOR_DIR;
    case PUBLIC_DIR;
}

// register here all builtins. These can be commands, Middleware, Authentications, etc.
if (!function_exists('allBuiltins')) {

    function allBuiltins(): Pionia\Pionia\Utils\Arrayable
    {
        return arr([
            'commands' => [
                'serve' => StartServer::class,
                'aliases' => ListAliasCommand::class,
                'generate_auth' => GenerateAuthenticationBackend::class,
                'generate_switch' => GenerateSwitch::class,
                'generate_service' => GenerateService::class,
            ],
            'authentications' => [
            ],
            'middlewares' => [
            ],
            // all these directories are relative to the root directory of the application
            'directories' => [
                DIRECTORIES::BASEPATH->name => '',
                DIRECTORIES::AUTHENTICATION_DIR->name => 'authentications',
                DIRECTORIES::MIDDLEWARE_DIR->name => 'middlewares',
                DIRECTORIES::COMMANDS_DIR->name => 'commands',
                DIRECTORIES::SERVICES_DIR->name => 'services',
                DIRECTORIES::SWITCHES_DIR->name => 'switches',
                DIRECTORIES::FRONTEND_DIR->name => 'static',
                DIRECTORIES::ENVIRONMENT_DIR->name => 'environment',
                DIRECTORIES::BOOTSTRAP_DIR->name => 'bootstrap',
                DIRECTORIES::VENDOR_DIR->name => 'vendor',
                DIRECTORIES::PUBLIC_DIR->name => 'public',
            ],
            'namespaces' => [
                NAMESPACES::AUTHENTICATION_NS->name =>'Application\Authentications',
                NAMESPACES::SERVICE_NS->name => 'Application\Services',
                NAMESPACES::SWITCH_NS->name => 'Application\Switches',
                NAMESPACES::COMMAND_NS->name => 'Application\Commands',
                NAMESPACES::MAIL_NS->name => 'Application\Mails',
                NAMESPACES::MIDDLEWARE_NS->name => 'Application\Middlewares'
            ],
            'aliases' => [
            ]
        ]);
    }
}

