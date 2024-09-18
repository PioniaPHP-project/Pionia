<?php

namespace Pionia\Cors;


use DI\DependencyException;
use DI\NotFoundException;
use Fruitcake\Cors\CorsService;
use Pionia\Base\PioniaApplication;
use Pionia\Collections\Arrayable;
use Pionia\Contracts\CorsContract;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
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
        $cors = $this->application->getOrDefault(CorsService::class, new CorsService($this->options->all()));
        $this->mergeAllowedOrigins();
        if ($this->preventBlockedOrigins($request, $response))
        {
            return;
        }
        // if by any chance the developer only permitted https connections, we should check if the request is secure
        if (!$this->resolveHttps($request, $response)) {
            return;
        }

        if ($cors->isCorsRequest($request) && $response) {
            $cors->varyHeader($response, 'Origin');
        }


        if ($cors->isPreflightRequest($request)) {
            $cors->handlePreflightRequest($response);
        }

        if ($cors->isCorsRequest($request)) {
            $cors->varyHeader($response, 'Origin');
        }

    }

    /**
     * Merge the allowed origins from the application configuration and let the cors middleware handle the rest
     * @return void
     */
    private function mergeAllowedOrigins(): void
    {
        $this->options->addAtKey('allowedOrigins', $this->application->getOrDefault('allowed_origins', []));
    }

    /**
     * prevent all blocked origins from accessing the application
     * @param Request $request
     * @param ?Response $response
     * @return bool
     */
    private function preventBlockedOrigins(Request $request, ?Response $response): bool
    {
        // we only want to do this if the request is cross-origin
        if ($request->headers->has('Origin')) {
            $serverOrigin = $request->headers->get('Origin');

            if (!$serverOrigin) {
                return false;
            }

            $blockedOrigins = $this->application->getOrDefault('blocked_origins', []);

            if ($blockedOrigins && is_array($blockedOrigins) && in_array($serverOrigin, $blockedOrigins)) {
                $response->setStatusCode(200);
                $res = response(403, 'Access to this resource is forbidden');
                $response->setContent($res->getPrettyResponse());
                return true;
            }
            return false;
        }
        return false;
    }

    private function resolveHttps($request, $response): bool
    {
        $httpsOnly = $this->application->getOrDefault('https_only', false);
        if (!$httpsOnly) {
            $httpsOnly = env('HTTPS_ONLY', false);
        }
        if ($httpsOnly && !$request->isSecure()) {
            $response->setStatusCode(200);
            $res = response(403, 'Only HTTPS connections are allowed');
            $response->setContent($res->getPrettyResponse());
            return false;
        }
        return true;
    }

    public function withSettingsNamed(?string $key  = 'cors'): ?array
    {
        $locals = env($key);
        if ($locals && is_array($locals)) {
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

