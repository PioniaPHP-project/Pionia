<?php

namespace Pionia\Core\Routing;

use Pionia\Core\Helpers\SupportedHttpMethods;
use Pionia\Core\Helpers\Utilities;
use Pionia\Core\Pionia;
use Pionia\Exceptions\ControllerException;
use Pionia\Response\BaseResponse;
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
    protected $routes;

    private string | null $controller = null;
    private string $basePath = '/api/v1/';

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
     * @throws ControllerException
     */
    private function resolveController(string |null $controller_ = null, bool $setAsCurrent = true): void
    {
        $controller = $controller_ ?? $this->controller;

        if (!$controller){
            throw new ControllerException('No controller defined');
        }

        $res = Utilities::extends($controller, 'Pionia\Core\BaseApiController');
        if ($res ==='NO_CLASS'){
            throw new ControllerException("Controller {$controller} class not found");
        } elseif ($res === 'DOES_NOT') {
            throw new ControllerException("Controller {$controller} does not implement BaseApiController");
        }

        if ($setAsCurrent){
            $this->controller = $controller;
        }
    }


    /**
     * @param string $action
     * @param string $name
     * @return BaseResponse|$this
     *
     * @deprecated - Use addSwitchFor instead
     */
    public function post(string $action, string $name): BaseResponse|static
    {
        try {
            if (!$this->controller) {
                throw new ControllerException('No controller defined, Hint: Have you called addGroup yet?');
            }
            $this->addRoute( $action, $name);
            return $this;
        } catch (ControllerException $e) {
            return BaseResponse::JsonResponse(500, $e->getMessage());
        }
    }

    /**
     * @throws ControllerException
     * @deprecated Use addSwitchFor instead
     */
    public function addGroup(string $controller, string $basePath = '/api/v1/'): static
    {
        $this->resolveController($controller);
        $this->basePath = $basePath;
        $this->addRoute('ping', 'ping', SupportedHttpMethods::GET, $controller);
        return $this;
    }

    /**
     * Adds a switch for a certain api version
     *
     * @param string $switch The switch to add
     * @param string|null $versionName The version name to add the switch to
     *
     * @return PioniaRouter
     * @throws ControllerException
     * @example
     * ```php
     * $router = new PioniaRouter();
     * $router->addSwitchFor('app\switches\MySwitch', 'v1');
     * ```
     */
    public function addSwitchFor(string $switch, ?string $versionName = 'v1'): PioniaRouter
    {
        $cleanVersion = $this->cleanVersion($versionName);
        $path = $this->apiBase.$cleanVersion.'/';
        $name = $cleanVersion.'_processor';
        $pingName = $cleanVersion.'_ping';

        if ($this->routes->get($name)){
            throw new ControllerException("Switch for version {$versionName} already exists");
        }

        $res = Utilities::extends($switch, 'Pionia\Core\BaseApiServiceSwitch');

        if ($res === 'NO_CLASS'){
            throw new ControllerException("Switch {$switch} class not found");
        } elseif ($res === 'DOES_NOT') {
            throw new ControllerException("Switch {$switch} does not implement BaseApiServiceSwitch");
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
     * @param string $action
     * @param string $name
     * @return BaseResponse|$this
     *
     * @deprecated - Use addSwitchFor instead
     */
    private function get(string $action, string $name): BaseResponse|static
    {
        try {
            if (!$this->controller) {
                throw new ControllerException('No controller defined, Hint: Have you called addGroup yet?');
            }
            $this->addRoute( $action, $name,SupportedHttpMethods::GET);
            return $this;
        } catch (ControllerException $e) {
            return BaseResponse::JsonResponse(500, $e->getMessage());
        }
    }

    /**
     * Cleans up the base url to set
     * @param string $base
     * @return string
     *
     * @deprecated - Was useful for v1.1.0 and below
     */
    private function cleanBase(string $base): string
    {
        if (!str_starts_with($base, "/") && str_ends_with($this->basePath, "/")){
            $base = '/'.$base;
        }

        if (!str_ends_with($base, "/")){
            $base .= "/";
        }

        return $base;
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

    /**
     * @throws ControllerException
     * @deprecated - Use addSwitchFor instead with the version name. This is just a helper method
     */
    private function addRoute(string $action, string $name, string | array $method = SupportedHttpMethods::POST, $controller = null, string | array $condition = ''): void
    {
        if ($controller) {
            $this->resolveController($controller);
        }
        $methods = [];
        if (is_string($method)) {
            $methods = [$method];
        } else if (is_array($method)) {
            $methods = $method;
        }

        if (!method_exists($this->controller, $action)) {
            throw new ControllerException("Action {$action} does not exist in controller {$this->controller}");
        }

        $route = new Route($this->basePath, [
            '_controller' => $this->controller . '::' . $action,
        ], [], [], null, [], $methods, $condition);

        $this->routes->add($name, $route);
    }

}


