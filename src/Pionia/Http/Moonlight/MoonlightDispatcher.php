<?php

namespace Pionia\Http\Moonlight;

use Pionia\Exceptions\ResourceNotFoundException;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Routing\SupportedHttpMethods;
use Pionia\Utils\CachedEndpoints;
use Throwable;

/**
 * Shared Moonlight service/action dispatch for HTTP, jobs, and WebSockets.
 */
final class MoonlightDispatcher
{
    use CachedEndpoints;

    /**
     * @param callable(): array<string, mixed>|null $serviceRegistry
     *
     * @throws Throwable
     */
    public static function dispatch(Request $request, callable $serviceRegistry): BaseResponse
    {
        try {
            $cached = self::cacheResponse($request);
            if ($cached) {
                return $cached;
            }
        } catch (Throwable $e) {
            logger()->warning($e);
        }

        [$service, $action] = self::resolveServiceAndAction($request);
        $registry = $serviceRegistry();
        $serviceKlass = is_array($registry) ? ($registry[$service] ?? null) : null;

        if ($serviceKlass && is_string($serviceKlass)) {
            $serviceKlass = container()->make($serviceKlass, ['request' => $request]);
        }

        if ($serviceKlass && method_exists($serviceKlass, 'processAction')) {
            return $serviceKlass->processAction($action, $service);
        }

        if ($serviceKlass) {
            throw new ResourceNotFoundException("Service {$service} is not a valid service");
        }

        throw new ResourceNotFoundException("Service {$service} not found");
    }

    /**
     * @return array{0: string, 1: string}
     */
    public static function resolveServiceAndAction(Request $request): array
    {
        if ($request->isMethod(SupportedHttpMethods::GET)) {
            $data = $request->attributes;
            $service = $data->getString('service') ?? throw new ResourceNotFoundException('Service not defined in request data');
            $action = $data->getString('action') ?? throw new ResourceNotFoundException('Action not defined in request data');

            return [$service, $action];
        }

        $data = $request->getData();
        $service = $data->getOrThrow('service', new ResourceNotFoundException('Service not defined in request data'));
        $action = $data->getOrThrow('action', new ResourceNotFoundException('Action not defined in request data'));

        return [(string) $service, (string) $action];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function requestFromPayload(array $payload, string $method = 'POST'): Request
    {
        return Request::create('/api/v1/', $method, $payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function dispatchPayload(array $payload, callable $serviceRegistry, string $method = 'POST'): BaseResponse
    {
        return self::dispatch(self::requestFromPayload($payload, $method), $serviceRegistry);
    }
}
