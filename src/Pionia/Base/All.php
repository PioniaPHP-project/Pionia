<?php

use Pionia\Builtins\Commands\Cache\CacheDeleteCommand;
use Pionia\Builtins\Commands\Cache\ClearCacheCommand;
use Pionia\Builtins\Commands\Cache\PruneCacheCommand;
use Pionia\Builtins\Commands\Context\ListAliasCommand;
use Pionia\Builtins\Commands\Generators\CreateMiddleware;
use Pionia\Builtins\Commands\Generators\GenerateAuthenticationBackend;
use Pionia\Builtins\Commands\Generators\GenerateCommand;
use Pionia\Builtins\Commands\Generators\GenerateService;
use Pionia\Builtins\Commands\Generators\GenerateSwitch;
use Pionia\Builtins\Commands\StartServer;
use Pionia\Collections\Arrayable;

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
    case CACHE_DIR;
    case STORAGE_DIR;
    case STATIC_DIR;
    case WELCOME_PAGE;
    case LOGS_DIR;
}

// register here all builtins. These can be commands, Middleware, Authentications, etc.
if (!function_exists('allBuiltins')) {

    function allBuiltins(): Arrayable
    {
        return arr([
            'commands' => [
                'serve' => StartServer::class,
                'aliases' => ListAliasCommand::class,
                'generate_auth' => GenerateAuthenticationBackend::class,
                'generate_switch' => GenerateSwitch::class,
                'generate_service' => GenerateService::class,
                'cache:prune'=>PruneCacheCommand::class,
                'cache:clear'=>ClearCacheCommand::class,
                'cache:delete'=>CacheDeleteCommand::class,
                'make:middleware' => CreateMiddleware::class,
                'make:command' => GenerateCommand::class,
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
                DIRECTORIES::CACHE_DIR->name => 'storage/cache',
                DIRECTORIES::LOGS_DIR->name => 'storage/logs',
                DIRECTORIES::STORAGE_DIR->name => 'storage',
                DIRECTORIES::STATIC_DIR->name => 'static',
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
                DIRECTORIES::WELCOME_PAGE->name => dirname(__DIR__) . DIRECTORY_SEPARATOR . 'templates/index.php'
            ],
            'providers' => [

            ]
        ]);
    }
}

