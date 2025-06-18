<?php

namespace Pionia\Http\Routing;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * This is the base routes class, it extends
 * the Symfony route collection class and is used to define routes in the framework
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 * */
class BaseRoutes extends RouteCollection {
    /**
     * @var array<string, Route>
     */
    private array $routes = [];


    public static function fromArray($array) : static
    {
        $instance = new static();
        $instance->routes = [];
        return $instance;
    }
}
