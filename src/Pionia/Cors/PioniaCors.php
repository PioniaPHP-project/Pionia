<?php

namespace Pionia\Pionia\Cors;


use DI\DependencyException;
use DI\NotFoundException;
use Fruitcake\Cors\CorsService;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Contracts\CorsContract;
use Pionia\Pionia\Utils\Arrayable;
use Pionia\Pionia\Http\Request\Request;
use Pionia\Pionia\Http\Response\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class PioniaCors implements CorsContract
{
    public PioniaApplication $application;

    public Arrayable $options;

    public function __construct(PioniaApplication $application)
    {
        $this->application = $application;
        $this->options = new Arrayable([]);
    }

    public function register(): static
    {
        $this->application->context->set(CorsService::class, function () {
            return new CorsService($this->options->all());
        });
        return $this;
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws NotFoundException
     * @throws ContainerExceptionInterface
     * @throws DependencyException
     */
    public function resolveRequest(Request $request, ?Response $response = null): void
    {
        $cors = $this->application->context->get(CorsService::class);
        if ($cors->isPreflightRequest($request)) {
            $cors->handlePreflightRequest($response);
        }

        if ($cors->isCorsRequest($request)) {
            $cors->varyHeader($response, 'Origin');
        }

    }

    public function withSettingsNamed(?string $key  = 'cors'): ?array
    {
        $locals = $this->application->env->get($key);
        if ($locals) {
            $this->options->merge($locals);
        }
        return $this->options->all();
    }

    public function options(?string $corsKey = 'cors'): array
    {
        $options = [
            'allowedOrigins' => ['*'],
            'allowedMethods' => ['GET', 'POST', 'OPTIONS'],
            'allowedHeaders' => [],
            'exposedHeaders' => [],
            'maxAge' => 0,
            'supportsCredentials' => false,
            'allowedOriginsPatterns' => [],
        ];

        $settings = $this->withSettingsNamed($corsKey);

        if ($settings) {
            $options = array_merge($options, $settings);
        }
        $this->options->merge($options);
        return $this->options->all();
    }

}

