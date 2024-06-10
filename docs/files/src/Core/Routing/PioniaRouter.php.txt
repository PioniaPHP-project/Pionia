<?php

namespace Pionia\core\routing;

use Pionia\core\helpers\SupportedHttpMethods;
use Pionia\core\helpers\Utilities;
use Pionia\core\Pionia;
use Pionia\exceptions\ControllerException;
use Pionia\response\BaseResponse;
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
 * $router = new PioniaRouter();
 * $router->addGroup('app\controller\MyController')
 *    ->post('myAction', 'myAction')
 *   ->get('myAction', 'myAction');
 * ```
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class PioniaRouter extends Pionia
{
    protected $routes;

    private string | null $controller = null;
    private string $basePath = '/api';

    public function getRoutes(): BaseRoutes
    {
        return $this->routes;
    }


    public function __construct(BaseRoutes | null $routes = null)
    {
        $this->routes = $routes ?? new BaseRoutes();

        $set = self::getServerSettings();

        if ($set && isset($set['baseurl'])){
            $this->basePath = $set['baseurl'];
        }
    }

    /**
     * @throws ControllerException
     */
    private function resolveController(string |null $_controller = null, $setAsCurrent = true): void
    {
        $controller = $_controller ?? $this->controller;

        if (!$controller){
            throw new ControllerException('No controller defined');
        }

        $res = Utilities::extends($controller, 'Pionia\core\BaseApiController');
        if ($res ==='NO_CLASS'){
            throw new ControllerException("Controller {$controller} class not found");
        } elseif ($res === 'DOES_NOT') {
            throw new ControllerException("Controller {$controller} does not implement BaseApiController");
        }

        if ($setAsCurrent){
            $this->controller = $controller;
        }
    }


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
     */
    public function addGroup(string $controller, string $basePath = 'v1/'): static
    {
        $this->resolveController($controller);
        $this->basePath = $this->basePath.$this->cleanBase($basePath);
        $this->addRoute('ping', 'ping', SupportedHttpMethods::GET, $controller);
        return $this;
    }


    public function get(string $action, string $name): BaseResponse|static
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
     */
    private function cleanBase(string $base)
    {
        if (!str_starts_with($base, "/") && str_ends_with($this->basePath, "/")){
            $base = '/'.$base;
        }

        if (!str_ends_with($base, "/")){
            $base .= "/";
        }

        return $base;
    }

    /**
     * @throws ControllerException
     */
    private function addRoute(string $action, string $name, string | array $method = SupportedHttpMethods::POST, $controller = null, $condition = ''): void
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


