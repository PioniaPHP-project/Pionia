<?php

namespace Pionia\Http\Routing;

use Pionia\Exceptions\ControllerException;
use Pionia\Http\Request\Request;
use ReflectionMethod;

/**
 * Dispatches matched routes to their controllers without Symfony HttpKernel.
 */
final class RouteDispatcher
{
    public function invoke(Request $request): mixed
    {
        $controller = $request->attributes->get('_controller');

        if (!is_string($controller) || $controller === '') {
            throw new ControllerException('Route is missing a controller.');
        }

        if (!str_contains($controller, '::')) {
            throw new ControllerException("Invalid controller reference: {$controller}");
        }

        [$class, $method] = explode('::', $controller, 2);

        if (!class_exists($class) || !method_exists($class, $method)) {
            throw new ControllerException("Controller {$controller} is not callable.");
        }

        $reflection = new ReflectionMethod($class, $method);

        if ($reflection->isStatic()) {
            return $class::$method($request);
        }

        return (new $class())->$method($request);
    }
}
