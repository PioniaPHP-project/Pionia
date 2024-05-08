<?php

namespace jetPhp\core\routing;

use jetPhp\core\helpers\SupportedHttpMethods;
use jetPhp\core\helpers\Utilities;
use jetPhp\exceptions\ControllerException;
use jetPhp\response\BaseResponse;
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
 * $router = new JetRouter();
 * $router->addGroup('app\controller\MyController')
 *    ->post('myAction', 'myAction')
 *   ->get('myAction', 'myAction');
 * ```
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class JetRouter
{
    protected $routes;

    private string | null $controller = null;
    private string $basePath = '/api/v1/';

    public function getRoutes(): BaseRoutes
    {
        return $this->routes;
    }

    public function __construct(BaseRoutes | null $routes = null)
    {
        $this->routes = $routes ?? new BaseRoutes();
    }

    private function resolveController(string |null $_controller = null, $setAsCurrent = true): static
    {
        $controller = $_controller ?? $this->controller;

        if (!$controller){
            throw new ControllerException('No controller defined');
        }

        $res = Utilities::extends($controller, 'jetPhp\core\BaseApiController');
        if ($res ==='NO_CLASS'){
            throw new ControllerException("Controller {$controller} class not found");
        } elseif ($res === 'DOES_NOT') {
            throw new ControllerException("Controller {$controller} does not implement BaseApiController");
        }

        if ($setAsCurrent){
            $this->controller = $controller;
        }
        return $this;
    }


    public function post(string $action, string $name)
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

    public function addGroup(string $controller, string $basePath = '/api/v1/'): static
    {
        $this->resolveController($controller);
        $this->basePath = $basePath;
        $this->addRoute('ping', 'ping', SupportedHttpMethods::GET, $controller);
        return $this;
    }


    public function get(string $action, string $name)
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
     * @throws ControllerException
     */
    private function addRoute(string $action, string $name, string | array $method = SupportedHttpMethods::POST, $controller = null, $condition = ''){
        if ($controller){
            $this->resolveController($controller);
        }
        $methods = [];
        if (is_string($method)){
            $methods = [$method];
        } else if (is_array($method)){
            $methods = $method;
        }

        if (!method_exists($this->controller, $action)) {
            throw new ControllerException("Action {$action} does not exist in controller {$this->controller}");
        }

        $route = new Route($this->basePath, [
            '_controller' => $this->controller.'::'.$action,
        ], [], [],null, [], $methods, $condition);

        $this->routes->add($name, $route);
        return $this;
    }

}
