<?php

namespace Pionia\Http\Base;

use Pionia\Auth\AuthenticationChain;
use Pionia\Contracts\CorsContract;
use Pionia\Contracts\KernelContract;
use Pionia\Http\HttpExceptionRenderer;
use Pionia\Http\MaintenanceMode;
use Pionia\Http\Monitoring\RequestMetrics;
use Pionia\Http\Base\Events\PreKernelBootEvent;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Utils\Microable;
use Pionia\Http\Response\BinaryFileResponse;
use Throwable;

/**
 * HTTP request kernel — routes, middleware, auth, and exception rendering.
 *
 * Entry flow for each request:
 * 1. {@see handle()} — maintenance gate, CORS preflight, then {@see prepareRequest()}
 * 2. {@see boot()} — `PreKernelBootEvent`, global middleware, authentication chain
 * 3. Route match + controller dispatch via {@see WebKernelRuntime}
 * 4. {@see terminate()} — metrics, response middleware, CORS headers, `prepare()`
 *
 * Uncaught throwables in step 3 are converted by {@see HttpExceptionRenderer} — they do
 * not bubble past {@see handle()}. Callers (FPM, RoadRunner worker, tests) send the
 * response returned from `handle()` / `terminate()` themselves.
 *
 * @see \Pionia\Base\WebApplication::handleRequest() Preferred application-level entry
 */
class WebKernel implements KernelContract
{
    use Microable;

    private readonly WebKernelRuntime $runtime;

    private readonly HttpExceptionRenderer $exceptionRenderer;

    public function __construct(?WebKernelRuntime $runtime = null)
    {
        $this->runtime = $runtime ?? new WebKernelRuntime();
        $this->exceptionRenderer = new HttpExceptionRenderer();
        WebKernelRuntime::registerShutdownFlush();
    }

    private function prepareRequest(Request $request): Response | BinaryFileResponse
    {
        $request = $this->boot($request);
        $this->runtime->syncContext($request);
        $request->attributes->add($this->runtime->match($request));

        return $this->runtime->dispatch()->invoke($request);
    }

    /**
     * Process one HTTP request end-to-end and return a prepared response.
     *
     * Does not call `send()` — the caller owns output (FPM, worker loop, or test).
     */
    public function handle(Request $request): Response | BinaryFileResponse
    {
        $started = hrtime(true);

        if ($maintenance = MaintenanceMode::respond($request)) {
            return $this->terminate($maintenance, $request, $started);
        }

        if ($cors = $this->corsEarlyResponse($request)) {
            return $this->terminate($cors, $request, $started);
        }

        try {
            $response = $this->prepareRequest($request);
        } catch (Throwable $e) {
            $response = $this->exceptionRenderer->render($e, $request);
        }

        return $this->terminate($response, $request, $started);
    }

    /**
     * Finalize a response: record metrics, run response middleware, apply CORS, prepare headers.
     *
     * Safe to call directly when building responses outside the normal dispatch path.
     */
    public function terminate(Response | BinaryFileResponse $response, Request $request, ?int $startedAt = null): Response | BinaryFileResponse
    {
        if ($startedAt !== null) {
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            RequestMetrics::record($request, $response, $durationMs);
        }

        if ($response instanceof Response) {
            $cors = realm()->getSilently(CorsContract::class);
            if ($cors instanceof CorsContract) {
                $cors->applyToResponse($response, $request);
            }

            $middlewareChain = realm()->getSilently(MiddlewareChain::class);
            if ($middlewareChain) {
                $middlewareChain->handle($request, $response);
            }
        }

        return $response->prepare($request);
    }

    private function corsEarlyResponse(Request $request): ?Response
    {
        $cors = realm()->getSilently(CorsContract::class);

        return $cors instanceof CorsContract ? $cors->handle($request) : null;
    }

    /**
     * Run global middleware and authentication before route dispatch.
     *
     * Fires {@see PreKernelBootEvent} first so listeners can mutate the request.
     */
    public function boot(Request $request): Request
    {
        event(new PreKernelBootEvent($this, $request), PreKernelBootEvent::name());

        $middlewareChain = realm()->getSilently(MiddlewareChain::class);
        if ($middlewareChain) {
            $middlewareChain->handle($request);
        }

        $authMiddleware = realm()->getSilently(AuthenticationChain::class);
        if ($authMiddleware) {
            $authMiddleware->handle($request);
        }

        return $request;
    }
}
