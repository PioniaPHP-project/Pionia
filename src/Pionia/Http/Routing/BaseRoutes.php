<?php

namespace Pionia\Http\Routing;

/**
 * This is the base routes class used to define routes in the framework.
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class BaseRoutes extends RouteTable
{
    public static function fromArray($array): static
    {
        return new static();
    }
}
