<?php

namespace Pionia\Pionia\Cors;


use DI\DependencyException;
use DI\NotFoundException;
use Fruitcake\Cors\CorsService;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Contracts\CorsInterface;
use Pionia\Pionia\Utilities\Arrayable;
use Pionia\Request\Request;
use Pionia\Response\Response;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class PioniaCors implements CorsInterface
{
    public PioniaApplication $application;

    public Arrayable $options;

    public function __construct(PioniaApplication $application)
    {
        $this->application = $application;
        $this->options = new Arrayable([]);
    }

    public function register(): void
    {
        $this->application->context->set(CorsService::class, function () {
            return new CorsService($this->options->all());
        });
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws NotFoundException
     * @throws ContainerExceptionInterface
     * @throws DependencyException
     */
    public function resolveRequest(Request $request, Response $response): void
    {
        $cors = $this->application->context->get(CorsService::class);
        if ($cors->isPreflightRequest($request)) {
            $cors->handlePreflightRequest($response);
        }

        if ($cors->isActualRequestAllowed($request)) {
            $cors->addActualRequestHeaders($response, $request);
        }

        if ($cors->isCorsRequest($request)) {
            $cors->varyHeader($response, 'Origin');
        }

        $cors->addActualRequestHeaders($response, $request);
    }

    public function withSettingsNamed(?string $key  = 'cors'): ?array
    {
        $locals = $this->application->env->get('cors');
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

