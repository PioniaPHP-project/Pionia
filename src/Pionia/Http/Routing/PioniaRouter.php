<?php

namespace Pionia\Http\Routing;

use Exception;
use InvalidArgumentException;
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
        $this->routes = $routes ?? new BaseRoutes();
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
     * Adds also the status endpoint for the switch. which can be accessed by hitting ping
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

        if (in_array('GET', $methods)){
            $getPath = $this->asGetApiVersion($path);
            $getRoute = RouteObject::get($getPath)
                ->controller(['_controller' => $controller]);
            foreach ($schemas as $schema) {
                $getRoute->addSchema($schema);
            }
            $gr = $getRoute->build();
            $this->routes->add('GET_'.$version, $gr);
        }

        $route = RouteObject::post($path)
            ->controller(['_controller' => $controller]);

        foreach ($schemas as $schema) {
            $route->addSchema($schema);
        }
        $postRoute = $route->build();
        $this->routes->add($version, $postRoute);
        $this->app->contextArrAdd(AppRealm::SWITCHES_TAGS, [$version => $switch]);
        $this->app->contextArrAdd(AppRealm::SERVICES_TAG, [$controller => $switch::registerServices()->all()]);
        return $this->addStatusEndpoint($version, $switch, $path);
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
        $postRoute = new Route($path, [
            '_controller' => $switch . '::processor',
        ], [], [], null, [], SupportedHttpMethods::POST);

        $this->routes->add($name, $postRoute);

        $pingRoute = new Route($path, [
            '_controller' => $switch . '::ping',
        ], [], [], null, [], SupportedHttpMethods::GET);

        $this->routes->add($pingName, $pingRoute);
        return $this;
    }

    private function cleanVersion(string $str): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', strtolower(trim($str)));
    }

}


