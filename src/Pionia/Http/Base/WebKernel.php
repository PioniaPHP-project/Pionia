<?php

namespace Pionia\Http\Base;

use Pionia\Auth\AuthenticationChain;
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

    public function handle(Request $request): Response | BinaryFileResponse
    {
        $started = hrtime(true);

        if ($maintenance = MaintenanceMode::respond($request)) {
            return $this->terminate($maintenance, $request, $started);
        }

        try {
            $response = $this->prepareRequest($request);
        } catch (Throwable $e) {
            $response = $this->exceptionRenderer->render($e, $request);
        }

        return $this->terminate($response, $request, $started);
    }

    public function terminate(Response | BinaryFileResponse $response, Request $request, ?int $startedAt = null): Response | BinaryFileResponse
    {
        if ($startedAt !== null) {
            $durationMs = (hrtime(true) - $startedAt) / 1_000_000;
            RequestMetrics::record($request, $response, $durationMs);
        }

        if ($response instanceof Response) {
            $middlewareChain = realm()->getSilently(MiddlewareChain::class);
            if ($middlewareChain) {
                $middlewareChain->handle($request, $response);
            }
        }

        return $response->prepare($request);
    }

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
