<?php

namespace Pionia\Realm;

use Pionia\Http\Routing\Router\DefaultRoutes;
use Pionia\Http\Routing\Router\RouteObject;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouteCollection;

trait RoutingTrait
{

    private function resolveRoutes(): static
    {

        $cachedRoutes = new RouteCollection();
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

    public function getRoutes(): RouteCollection
    {
        return $this->getSilently(self::APP_ROUTES_TAG) ?? $this->getCache(self::APP_ROUTES_TAG, true) ?? new RouteCollection();
    }

    public function addRoutes(RouteCollection $collection): static {
        $routes = $this->getRoutes();

        $routes->addCollection($collection);
        $this->updateCache(self::APP_ROUTES_TAG, $routes, true, 10);
        $this->set(self::APP_ROUTES_TAG, $routes);
        return $this;
    }
}
