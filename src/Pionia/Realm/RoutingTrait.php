<?php

namespace Pionia\Realm;

use Pionia\Http\Routing\RouteTable;
use Pionia\Http\Routing\Router\DefaultRoutes;
use Pionia\Http\Routing\Router\RouteObject;

trait RoutingTrait
{

    private function resolveRoutes(): static
    {

        $cachedRoutes = new RouteTable();
        $this->set(self::APP_ROUTES_TAG, $cachedRoutes);

        (new DefaultRoutes())->collect($this);
        DefaultRoutes::collectStaticRoutes($this);
        return $this;
    }

    /**
     * This just snitches in the pionia logo and favicon
     * @return $this
     */
    private function addDefaultStaticFiles(): static
    {
        return $this;
    }

    public function addRoute(string $name, RouteObject $routeObject): static {
        $builtRouteObject = $routeObject->build();
        $routes = $this->getRoutes();
        $routes->add($name, $builtRouteObject);
        $this->set(self::APP_ROUTES_TAG, $routes);
        $this->setCache(self::APP_ROUTES_TAG, $routes);
        return $this;
    }

    public function getRoutes(): RouteTable
    {
        return $this->getSilently(self::APP_ROUTES_TAG) ?? $this->getCache(self::APP_ROUTES_TAG, true) ?? new RouteTable();
    }

    public function addRoutes(RouteTable $collection): static {
        $routes = $this->getRoutes();

        $routes->addCollection($collection);
        $this->updateCache(self::APP_ROUTES_TAG, $routes, true, 10);
        $this->set(self::APP_ROUTES_TAG, $routes);
        return $this;
    }
}
