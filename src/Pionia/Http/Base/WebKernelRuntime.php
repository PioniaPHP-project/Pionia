<?php

namespace Pionia\Http\Base;

use Pionia\Http\Monitoring\RequestMetrics;
use Pionia\Http\Routing\CompiledRouteMatcher;
use Pionia\Http\Routing\RouteDispatcher;
use Pionia\Http\Routing\RouteMatcher;
use Pionia\Http\Request\Request;

/**
 * Reuses routing and dispatch objects across requests in persistent runtimes.
 */
final class WebKernelRuntime
{
    private ?RouteMatcher $matcher = null;

    private ?RouteDispatcher $dispatch = null;

    public function matcher(): RouteMatcher
    {
        return $this->matcher ??= CompiledRouteMatcher::create();
    }

    public function syncContext(Request $request): void
    {
        // Native matcher reads path and method directly from the request.
    }

    public function dispatch(): RouteDispatcher
    {
        return $this->dispatch ??= new RouteDispatcher();
    }

    /**
     * @return array<string, mixed>
     */
    public function match(Request $request): array
    {
        return $this->matcher()->match($request->getPathInfo(), $request->getMethod());
    }

    public static function registerShutdownFlush(): void
    {
        register_shutdown_function(static function (): void {
            RequestMetrics::flush();
        });
    }
}
