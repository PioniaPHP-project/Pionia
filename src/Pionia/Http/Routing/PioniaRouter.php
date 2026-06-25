<?php

namespace Pionia\Http\Routing;

use Exception;
use InvalidArgumentException;
use Pionia\Collections\Arrayable;
use Pionia\Contracts\BaseSwitchContract;
use Pionia\Http\Routing\Router\RouteObject;
use Pionia\Http\Switches\BaseApiServiceSwitch;
use Pionia\Realm\AppRealm;
use Pionia\Realm\RealmContract;
use SebastianBergmann\LinesOfCode\IllogicalValuesException;
use Symfony\Component\Routing\Route;


/**
 * This is the basis for defining routes in the application.
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class PioniaRouter implements RouterContract
{
    protected BaseRoutes $routes;

    private string $apiBase = '/api/';

    private ?RealmContract $app;

    /**
     * PioniaRouter constructor.
     *
     * @param RealmContract $app The application instance
     */

    public function __construct(RealmContract $app)
    {
        $this->routes = new BaseRoutes();
        $this->app = $app;
        $this->apiBase = $app->env($app::APP_API_BASE_TAG, $this->apiBase) ?? $app->getOrDefault($app::APP_API_BASE_TAG, $this->apiBase);
    }

    /**
     * Update the base path for the API routes.
     * Defaults to `/api/`.
     * @param string|null $base
     * @return $this
     */
    public function base(?string $base = null): static
    {
        if ($base) {
            $base = trim($base);
            if (!str_starts_with($base, '/')) {
                $base = '/' . $base;
            }
            if (!str_ends_with($base, '/')) {
                $base .= '/';
            }
            $this->apiBase = $base;
            $this->app->set($this->app::APP_API_BASE_TAG, $base);
        }
        return $this;
    }

    private function addStatusEndpoint(string $version, string $service, string $path): static
    {
        $route = RouteObject::get($path."ping")
            ->controller(['_controller' => $service . '::ping'])
            ->addSchema('https')
            ->addSchema('http')
            ->addMethod('GET')
            ->build();
        $this->routes->add($version . '_ping', $route);

        $this->app->addRoutes($this->routes);
        return $this;
    }


    /**
     * Adds a switch for a certain api version
     * This is the new implementation of the `addSwitchFor` method.
     * addSwitchFor and `wireTo` is deprecated fully and will be removed in the next version.
     *
     * Adds also the status endpoint for the switch at `{apiBase}{version}/ping` (e.g. `/api/v1/ping`).
     */
    public function switch(string $switch, string $version, ?array $schemas = ['https', 'http'], ?array $methods = ['POST', 'GET']): static
    {
        if (!is_a($switch, BaseSwitchContract::class, true)){
            throw new InvalidArgumentException($switch . ' is not a valid Pionia switch');
        }

        if (!is_array($methods) || empty($methods)) {
            $methods = ['POST', 'GET'];
        }

        if (!is_array($schemas) || empty($schemas)){
            $schemas = ['https', 'http'];
        }

        $path = $this->asApiVersion($version);

        $controller = $switch . '::processor';

        if (in_array('GET', $methods)) {
            foreach ($this->trailingSlashVariants($this->asGetApiVersion($path)) as $index => $getPath) {
                $getRoute = RouteObject::get($getPath)
                    ->controller(['_controller' => $controller]);
                foreach ($schemas as $schema) {
                    $getRoute->addSchema($schema);
                }
                $this->routes->add($this->routeVariantName('GET_' . $version, $index), $getRoute->build());
            }
        }

        foreach ($this->trailingSlashVariants($path) as $index => $postPath) {
            $route = RouteObject::post($postPath)
                ->controller(['_controller' => $controller]);

            foreach ($schemas as $schema) {
                $route->addSchema($schema);
            }
            $this->routes->add($this->routeVariantName($version, $index), $route->build());
        }

        if (in_array('GET', $methods)) {
            foreach ($this->trailingSlashVariants($path) as $index => $overviewPath) {
                $overviewRoute = RouteObject::get($overviewPath)
                    ->controller([
                        '_controller' => $switch . '::overview',
                        'apiVersion' => $version,
                    ]);
                foreach ($schemas as $schema) {
                    $overviewRoute->addSchema($schema);
                }
                $this->routes->add($this->routeVariantName($version . '_overview', $index), $overviewRoute->build());
            }
        }

        $this->registerSwitchContext($version, $switch, $controller);
        return $this->addStatusEndpoint($version, $switch, $path)
            ->addCatalogEndpoint($version, $switch, $path);
    }

    private function addCatalogEndpoint(string $version, string $switch, string $path): static
    {
        foreach (['__catalog', '__catalog/'] as $suffix) {
            $route = RouteObject::get($path . $suffix)
                ->controller(['_controller' => $switch . '::catalog'])
                ->addSchema('https')
                ->addSchema('http')
                ->addMethod('GET')
                ->build();
            $this->routes->add($version . '_catalog' . str_replace('/', '_', $suffix), $route);
        }

        $this->app->addRoutes($this->routes);

        return $this;
    }

    private function registerSwitchContext(string $version, string $switch, string $controller): void
    {
        $switches = $this->app->getOrDefault(AppRealm::SWITCHES_TAGS, arr([]));
        if (!$switches instanceof Arrayable) {
            $switches = arr((array) $switches);
        }
        $switches->add($version, $switch);
        $this->app->set(AppRealm::SWITCHES_TAGS, $switches);

        $services = $this->app->getOrDefault(AppRealm::SERVICES_TAG, arr([]));
        if (!$services instanceof Arrayable) {
            $services = arr((array) $services);
        }
        $services->add($controller, $switch::registerServices()->all());
        $this->app->set(AppRealm::SERVICES_TAG, $services);
    }

    /**
     * @internal For internal use only.
     * Returns the routes object that can be added to the container
     */
    function get(): BaseRoutes
    {
        return $this->routes;
    }

    protected function asApiVersion(string $version): string
    {
        $cleanVersion = $this->cleanVersion($version);
        return $this->apiBase . $cleanVersion . '/';
    }

    protected function asGetApiVersion(string $path): string
    {
        if (!str_ends_with($path, "/")){
            $path .= "/";
        }

        return $path."{service}/{action}/";
    }

    /**
     * Adds a switch for a certain api version
     *
     * @deprecated see new implementation `switch` method
     * @see PioniaRouter::switch()
     * @param string $switch The switch to add
     * @param string|null $versionName The version name to add the switch to
     *
     * @return PioniaRouter
     * @throws Exception
     * @example
     * ```php
     * $router = new PioniaRouter();
     * $router->addSwitchFor('app\switches\MySwitch', 'v1');
     * ```
     */
    public function wireTo(string $switch, ?string $versionName = 'v1'): static
    {
        $cleanVersion = $this->cleanVersion($versionName);
        $path = $this->apiBase.$cleanVersion.'/';
        $name = $cleanVersion.'_processor';
        $pingName = $cleanVersion.'_ping';

        if ($this->routes->get($name)){
            throw new Exception("Switch for version {$versionName} already exists");
        } else if (!is_subclass_of($switch, BaseApiServiceSwitch::class)){
            throw new Exception("Switch {$switch} does not extend BaseApiServiceSwitch");
        }

        // add the only post route
        foreach ($this->trailingSlashVariants($path) as $index => $variantPath) {
            $postRoute = new Route($variantPath, [
                '_controller' => $switch . '::processor',
            ], [], [], null, [], SupportedHttpMethods::POST);

            $this->routes->add($this->routeVariantName($name, $index), $postRoute);
        }

        foreach ($this->trailingSlashVariants($path) as $index => $variantPath) {
            $pingRoute = new Route($variantPath, [
                '_controller' => $switch . '::ping',
            ], [], [], null, [], SupportedHttpMethods::GET);

            $this->routes->add($this->routeVariantName($pingName, $index), $pingRoute);
        }
        return $this;
    }

    /**
     * @return list<string>
     */
    private function trailingSlashVariants(string $path): array
    {
        $without = rtrim($path, '/');

        if ($without === '') {
            return ['/'];
        }

        return [$without, $without . '/'];
    }

    private function routeVariantName(string $base, int $index): string
    {
        return $index === 0 ? $base . '_noslash' : $base;
    }

    private function cleanVersion(string $str): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', strtolower(trim($str)));
    }

}


