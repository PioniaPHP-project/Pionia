<?php

namespace Pionia\Http\Routing;

use Exception;
use Pionia\Http\Switches\BaseApiServiceSwitch;
use Symfony\Component\Routing\Route;


/**
 * This is the basis for defining routes in the application.
 *
 *
 * You can only add `post` and `get` routes as that what the framework tends to support.
 *
 * If you need more methods, you can add them to the SupportedHttpMethods class and implement them here.
 * However, this is meant for core framework developers only.
 *
 * @example
 * ```php
 * // deprecated version
 * $router = new PioniaRouter();
 * $router->addGroup('app\controller\MyController')
 *    ->post('myAction', 'myAction')
 *   ->get('myAction', 'myAction');
 *
 * // new version
 * $router = new PioniaRouter();
 * $router->addSwitchFor('app\switches\MySwitch', 'v1');
 * ```
 *
 *
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class PioniaRouter
{
    protected BaseRoutes $routes;

    private string $apiBase = '/api/';

    public function getRoutes(): BaseRoutes
    {
        return $this->routes;
    }

    public function __construct(BaseRoutes | null $routes = null)
    {
        $this->routes = $routes ?? new BaseRoutes();
    }

    /**
     * Adds a switch for a certain api version
     *
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
    public function wireTo(string $switch, ?string $versionName = 'v1'): PioniaRouter
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

    /**
     * @throws Exception
     */
    public function addSwitchFor(string $switch, ?string $versionName = 'v1'): PioniaRouter
    {
        return $this->wireTo($switch, $versionName);
    }

    private function cleanVersion(string $str): string
    {
        if (str_starts_with($str, "/")){
            $str = substr($str, 1);
        }

        if (str_ends_with($str, "/")){
            $str = substr($str, 0, -1);
        }

        return $str;
    }

}


