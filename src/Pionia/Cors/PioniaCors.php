<?php

namespace Pionia\Cors;


use Pionia\Collections\Arrayable;
use Pionia\Contracts\CorsContract;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Utils\Support;

/***
 * Handles cors in Pionia requests and applications.
 * Supports allowing specific origins and blocking certain origins from accessing your endpoint
 */
class PioniaCors implements CorsContract
{
    private ?Arrayable $settings;


    public function handle($request): void
    {
        $this
            ->addAllowedHeaders()
            ->addAllowedMethods()
            ->addAllowedOrigin()
            ->addMaxAge()
            ->handlePreflight($request)
            ->blockNonAllowedOrigins($request)
            ->resolveHttps($request);
    }

    public function __construct()
    {
        $this->settings = arr(array(
            'allowed_origins' => '*',
            'allowed_headers' => '*',
            'credentials' => 'true',
            'max_age' => 86400,
        ));

        $this->settings->merge(arr(env('cors', [])));
        // start adding the headers
    }

    private function isPreflight(Request $request): bool
    {
        return $request->isMethod('OPTIONS');
    }

    private function blockNonAllowedOrigins(Request $request): ?static
    {
        if ($this->isPreflight($request)){
            return $this;
        }

        $blocked = app()->getSilently("blocked_origins");
        $serverOrigin = env('HTTP_ORIGIN', '');
        if ($blocked && count($blocked) > 0) {
            if (in_array($serverOrigin, $blocked) || in_array('*', $blocked)) {
                $response = new Response();
                $response->setStatusCode(Response::HTTP_OK);
                $res = response(403, 'Traffic from '. $serverOrigin .' is currently not accepted by '. app()->getAppName());
                $response->setContent($res->getPrettyResponse());
                $response->prepare($request)->send();
                exit();
            }
        }
        return $this;
    }

    private function addAllowedOrigin(): static
    {
        $allowedOrigins = $this->settings->get('allowed_origins', '*');

        $allowOrigin = '*';

        if ($allowedOrigins !== '*') {

            $allowOriginArray = explode(',', $allowedOrigins);

            $contextAllowedOrigins = app()->getSilently('allowed_origins');

            if ($contextAllowedOrigins && !empty($allowOriginArray)) {
                // remove "*" from the array
                $allowOriginArray = array_values(array_filter($allowOriginArray, function ($value) {
                    return $value !== '*';
                }));

                $allowOriginArray = array_merge($allowOriginArray, $contextAllowedOrigins);
            }

            $serverOrigin = env('HTTP_ORIGIN', '');

            if (in_array($serverOrigin, $allowOriginArray)) {
                $allowOrigin = $allowedOrigins;
            } else {
                $allowOrigin = '';
            }
        }
        header('Access-Control-Allow-Origin: '.$allowOrigin);
        return $this;
    }

    private function addAllowedHeaders(): static
    {
        $allowedHeaders = $this->settings->get('allowed_headers', '*');
        header('Access-Control-Allow-Headers: '.$allowedHeaders);
        return $this;
    }

    private function addAllowedMethods(): static
    {
        header('Access-Control-Allow-Methods: '.$this->settings->get('allowed_methods'));
        return $this;
    }

    private function handlePreflight(Request $request): ?static
    {
        if ($request->isMethod('OPTIONS')){
            $response = new Response();
            $response->setStatusCode(201);
            $response->prepare($request)->send();
            exit();
        }

        return $this;
    }

    private function addMaxAge(): static
    {
        $maxAge = $this->settings->get('max_age', 86400);
        header('Access-Control-Max-Age: '.$maxAge);
        return $this;
    }

    private function resolveHttps($request): ?static
    {
        $httpsOnly = app()->getOrDefault('https_only', false);
        if (!$httpsOnly) {
            $httpsOnly = env('HTTPS_ONLY', false);
        }
        if ($httpsOnly && !$request->isSecure()) {
            $response = new Response();
            $response->setStatusCode(200);
            $res = response(403, 'Only HTTPS connections are allowed');
            $response->setContent($res->getPrettyResponse());
            $response->prepare($request)->send();
            exit(1);
        }

        return $this;
    }
}

