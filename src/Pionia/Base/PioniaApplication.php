<?php

namespace Pionia\Pionia\Base;

use DI\Container;
use Pionia\Pionia\Base\Utils\AppHelpersTrait;
use Pionia\Pionia\Base\Utils\ApplicationHooks;
use Pionia\Pionia\Base\Utils\EnvResolver;
use Pionia\Pionia\Base\Utils\Microable;
use Pionia\Pionia\Base\Utils\PathsTrait;
use Pionia\Pionia\Base\Utils\PioniaApplicationType;
use Pionia\Pionia\Utilities\Arrayable;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;


class PioniaApplication
{
    use ApplicationHooks,
        AppHelpersTrait,
        Microable,
        PathsTrait;

    /**
     * Application type
     * @var PioniaApplicationType
     */
    protected PioniaApplicationType $applicationType = PioniaApplicationType::REST;

    /**
     * Application container context
     * @var ContainerInterface
     */
    public ContainerInterface $context;

    public Arrayable $env;

    /**
     * booted commands
     * @var Arrayable
     */
    protected Arrayable $commands;

    protected EnvResolver $envResolver;

    public function __construct(Container $container, ?EnvResolver $env = null)
    {
        $this->context = $container;
        $this->envResolver = $env ?? new EnvResolver();
        $this->env = $this->envResolver->getEnv();
    }

    public function getEnv(): Arrayable
    {
        return $this->env;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    public function runIn(PioniaApplicationType $type): PioniaApplication
    {

        $this->applicationType = $type;

        // this runs before the application is booted
        $this->boot();

        // this is where the actual running of the application happens
//        $this->bootstrapMiddlewares();
//        $this->bootstrapAuthentications();
//        $this->bootstrapCommands();


        // this runs after the application is booted
        $this->booted();

        return $this;
    }

    private function bootstrapMiddlewares(): void
    {

        $this->context->set('middlewares', new Arrayable());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function bootstrapAuthentications(): void
    {
        if ($this->context->has('authentications')) {
            $this->authenticationBackends->merge($this->context->get('authentications'));
        }
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    private function bootstrapCommands(): void
    {
        if ($this->context->has('commands')) {
            $this->commands->merge($this->context->get('commands'));
        }
    }


}
