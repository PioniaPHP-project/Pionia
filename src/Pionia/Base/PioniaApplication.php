<?php

namespace Pionia\Pionia\Base;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use JetBrains\PhpStorm\NoReturn;
use Pionia\Pionia\Base\Utils\AppHelpersTrait;
use Pionia\Pionia\Base\Utils\ApplicationLifecycleHooks;
use Pionia\Pionia\Base\Utils\Containable;
use Pionia\Pionia\Base\Utils\EnvResolver;
use Pionia\Pionia\Base\Utils\Microable;
use Pionia\Pionia\Base\Utils\PathsTrait;
use Pionia\Pionia\Base\Utils\PioniaApplicationType;
use Pionia\Pionia\Utilities\Arrayable;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;


class PioniaApplication implements LoggerAwareInterface
{
    use ApplicationLifecycleHooks,
        AppHelpersTrait,
        Microable,
        PathsTrait,
        Containable;

    public string $appName = 'Pionia';

    /**
     * Application type
     * @var ?PioniaApplicationType
     */
    protected ?PioniaApplicationType $applicationType = PioniaApplicationType::REST;

    /**
     * Environment variables
     * @var ?Arrayable
     */
    public ?Arrayable $env;

    /**
     * Logger instance
     * @var ?LoggerInterface
     */
    public ?LoggerInterface $logger;

    /**
     * Environment resolver
     * @var ?EnvResolver
     */
    protected ?EnvResolver $envResolver;

    /**
     * PioniaApplication constructor.
     * @param Container $container
     * @param EnvResolver|null $env
     * @param string $envDir
     */
    public function __construct(Container $container, ?EnvResolver $env = null, string $envDir = 'environment')
    {
        try {

            $this->context = $container;
            // if we passed the environment, we use it, otherwise we get it from the context
            $this->envResolver = $env ?? $this->context->has(EnvResolver::class) ? $this->context->get(EnvResolver::class) : new EnvResolver($envDir);
            $this->env = $this->envResolver->getEnv();
            // we set the env to the context
            $this->context->set('env', $this->env);
            // we set the env to the context
            $this->context->set(EnvResolver::class, $this->env);
            // we populate the app name from the env or set it to the default
            $this->context->set('APP_NAME', $this->env->get("APP_NAME") ?? $this->appName);

            $this->context->set('BASE_DIR', $this->appRoot());

            $this->context->set('LOGS_DIR', $this->appRoot($this->env->get('LOGS_DIR') ?? 'logs'));
        } catch (DependencyException|NotFoundException|NotFoundExceptionInterface|ContainerExceptionInterface  $e) {
            $this->shutdown();
        }
    }

    public function getEnv(): Arrayable
    {
        return $this->env;
    }

    public function collectAuthenticationBackend()
    {
    }

    /**
     */
    public function runIn(PioniaApplicationType $type): PioniaApplication
    {

        $this->applicationType = $type;

        $this->context->set(PioniaApplicationType::class, $type);

        // this runs before the application is booted
        $this->boot();

        // this is where the actual running of the application happens
        try {
            $this->bootstrapMiddlewares();
            $this->bootstrapAuthentications();
            $this->bootstrapCommands();
        } catch (ContainerExceptionInterface|NotFoundExceptionInterface $e) {
            if ($this->has(LoggerInterface::class)) {
                $this->getSilently(LoggerInterface::class)->error($e->getMessage());
            }
            $this->shutdown();
        }
        // this runs after the application is booted
        $this->booted();

        return $this;
    }

    /**
     */
    private function bootstrapMiddlewares(): void
    {
        $middlewares = new Arrayable();
        // collect all the middlewares from the environment and the context
        $this->env->has('middlewares') && $middlewares->merge($this->env->get('middlewares'));

        $scopedMiddlewares = $this->getOrDefault('middlewares', []);

        if ($scopedMiddlewares instanceof Arrayable) {
            $middlewares->merge($scopedMiddlewares->all());
        } elseif (is_array($scopedMiddlewares)) {
            $middlewares->merge($scopedMiddlewares);
        }

        $this->context->set('middlewares', $middlewares);
    }

    private function bootstrapAuthentications(): void
    {
        $authentications = new Arrayable();
        // collect all the middlewares from the environment and the context
        $this->env->has('authentications') && $authentications->merge($this->env->get('authentications'));

        $scoped = $this->getOrDefault('authentications', []);

        if ($scoped instanceof Arrayable) {
            $authentications->merge($scoped->all());
        } elseif (is_array($scoped)) {
            $authentications->merge($scoped);
        }

        $this->context->set('authentications', $authentications);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function bootstrapCommands(): void
    {
        $commands = new Arrayable();
        // collect all the middlewares from the environment and the context
        $this->env->has('commands') && $commands->merge($this->env->get('commands'));

        if ($this->context->has('commands')) {
            $scoped = $this->context->get('commands');
            if ($scoped instanceof Arrayable) {
                $commands->merge($scoped->all());
            } elseif (is_array($scoped)) {
                $commands->merge($scoped);
            }
        }

        $this->context->set('commands', $commands);
    }


    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
        $this->context->set(LoggerInterface::class, $logger);
    }

    protected function report(string $format, string $message, ?array $data = []): void
    {
        $format = strtolower($format);
        $this->logger?->$format($message, $data);
    }

    #[NoReturn]
    public function shutdown(int $status = 1): void
    {
        $this->report('info', 'Shutting down the application');

        $this->report('info', 'Running the pre-shutdown terminating hook');
        $this->terminating();
        $this->report('info', 'Pre-termination hook completed');
        $this->report('info', 'Clearing the context');
        $this->context = null;
        $this->env = null;
        $this->envResolver = null;
        $this->applicationType = null;
        $this->report('info', 'Clearing the context completed');
        $this->report('info', 'Running the post-shutdown terminated hook');
        $this->terminated();
        $this->report('info', 'Post-termination hook completed');
        $this->report('info', 'Shutting down the application completed, exiting now, bye!');
        $this->logger = null;
        exit($status);
    }
}
