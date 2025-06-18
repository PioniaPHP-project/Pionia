<?php

namespace Pionia\Realm;

use Pionia\Http\Routing\BaseRoutes;
use Pionia\Http\Routing\Router\DefaultRoutes;
use Pionia\Http\Routing\Router\RouteObject;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouteCollection;
use function DI\add;

trait RoutingTrait
{

    private function resolveRoutes(): static
    {

//        $skipCached = true;
//        if ($this->isDebug()){
//            $skipCached = false;
//        }

//        if (!$skipCached){
//            // we recollect routes afresh
//            $routes = new BaseRoutes();
//            $this->set(self::APP_ROUTES_TAG, function () use ($routes) {
//                return $routes;
//            });
//            $this->cache(self::APP_ROUTES_TAG, $routes, 2000);
//        } else {
//            if ($this->hasCache(self::APP_ROUTES_TAG)) {
//                $cachedRoutes = $this->getCache(self::APP_ROUTES_TAG, true);
//            } else {
                $cachedRoutes = new RouteCollection();
//                $this->cache(self::APP_ROUTES_TAG, $cachedRoutes, 2000);
//            }
            $this->set(self::APP_ROUTES_TAG, $cachedRoutes);


//        }

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
        $staticFolder = $this->getSilently("STATIC_DIR");
        if ($staticFolder){
            $favicon = $staticFolder.DIRECTORY_SEPARATOR.'favicon.ico';
            $fileSystem = new Filesystem();
            if (!file_exists($favicon)){
                $location = __DIR__.DIRECTORY_SEPARATOR.'favicon.ico';
                if ($fileSystem->exists($location)){
                    $fileSystem->copy($location, $favicon);
                }
            }

            $brand = $staticFolder.DIRECTORY_SEPARATOR.'pionia_logo.webp';
            if (!file_exists($brand)){
                $new_location = __DIR__.DIRECTORY_SEPARATOR.'pionia_logo.webp';
                if ($fileSystem->exists($new_location)){
                    $fileSystem->copy($new_location, $brand);
                }
            }
        }
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
