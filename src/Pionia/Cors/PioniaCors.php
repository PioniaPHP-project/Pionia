<?php

namespace Pionia\Cors;

use Pionia\Collections\Arrayable;
use Pionia\Contracts\CorsContract;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;

/***
 * Handles cors in Pionia requests and applications.
 * Supports allowing specific origins and blocking certain origins from accessing your endpoint
 */
class PioniaCors implements CorsContract
{
    private ?Arrayable $settings;

    /**
     * Short-circuit responses for preflight, blocked origins, or HTTPS-only enforcement.
     * Returns null when the request should continue through the kernel.
     */
    public function handle(Request $request): ?Response
    {
        if ($this->isPreflight($request)) {
            $response = new Response('', 201);
            $this->applyCorsHeaders($response, $request);

            return $response;
        }

        if ($blocked = $this->blockedOriginResponse($request)) {
            $this->applyCorsHeaders($blocked, $request);

            return $blocked;
        }

        if ($httpsOnly = $this->httpsOnlyResponse($request)) {
            $this->applyCorsHeaders($httpsOnly, $request);

            return $httpsOnly;
        }

        return null;
    }

    /**
     * Attach CORS headers to an outgoing response (worker-safe; no header() or exit()).
     */
    public function applyToResponse(Response $response, Request $request): Response
    {
        $this->applyCorsHeaders($response, $request);

        return $response;
    }

    public function __construct()
    {
        $this->settings = arr([
            'allowed_origins' => '*',
            'allowed_headers' => '*',
            'credentials' => 'true',
            'max_age' => 86400,
        ]);

        $this->settings->merge(arr(env('cors', [])));
    }

    private function isPreflight(Request $request): bool
    {
        return $request->isMethod('OPTIONS');
    }

    private function blockedOriginResponse(Request $request): ?Response
    {
        $blocked = app()->getSilently('blocked_origins');
        $serverOrigin = $this->requestOrigin($request);

        if ($blocked && count($blocked) > 0) {
            if (in_array($serverOrigin, $blocked, true) || in_array('*', $blocked, true)) {
                $response = new Response();
                $response->setStatusCode(200);
                $res = response(403, 'Traffic from ' . $serverOrigin . ' is currently not accepted by ' . app()->getAppName());
                $response->setContent($res->getPrettyResponse());

                return $response;
            }
        }

        return null;
    }

    private function applyCorsHeaders(Response $response, Request $request): void
    {
        $response->headers->set('Access-Control-Allow-Origin', $this->resolveAllowOrigin($request));
        $response->headers->set('Access-Control-Allow-Headers', (string) $this->settings->get('allowed_headers', '*'));
        $response->headers->set(
            'Access-Control-Allow-Methods',
            (string) $this->settings->get('allowed_methods', 'GET, POST, OPTIONS'),
        );
        $response->headers->set('Access-Control-Max-Age', (string) $this->settings->get('max_age', 86400));
    }

    private function resolveAllowOrigin(Request $request): string
    {
        $allowedOrigins = $this->settings->get('allowed_origins', '*');

        if ($allowedOrigins === '*') {
            return '*';
        }

        $allowOriginArray = explode(',', (string) $allowedOrigins);
        $contextAllowedOrigins = app()->getSilently('allowed_origins');

        if ($contextAllowedOrigins && $allowOriginArray !== []) {
            $allowOriginArray = array_values(array_filter(
                $allowOriginArray,
                static fn ($value) => $value !== '*',
            ));
            $allowOriginArray = array_merge($allowOriginArray, $contextAllowedOrigins);
        }

        $serverOrigin = $this->requestOrigin($request);

        if (in_array($serverOrigin, $allowOriginArray, true)) {
            return (string) $allowedOrigins;
        }

        return '';
    }

    private function httpsOnlyResponse(Request $request): ?Response
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

            return $response;
        }

        return null;
    }

    private function requestOrigin(Request $request): string
    {
        return (string) ($request->headers->get('Origin') ?? env('HTTP_ORIGIN', ''));
    }
}
