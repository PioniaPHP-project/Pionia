<?php

namespace Pionia\Http\Routing;

use Pionia\Realm\AppRealm;

/**
 * Builds and caches a compiled route matcher for the application route table.
 */
final class CompiledRouteMatcher
{
    private static ?RouteMatcher $matcher = null;

    public static function reset(): void
    {
        self::$matcher = null;
    }

    public static function create(): RouteMatcher
    {
        if (self::$matcher !== null) {
            return self::$matcher;
        }

        $routes = app()->getSilently(AppRealm::APP_ROUTES_TAG);
        if (!$routes instanceof RouteTable) {
            $routes = new RouteTable();
        }

        return self::$matcher = new RouteMatcher($routes);
    }
}
