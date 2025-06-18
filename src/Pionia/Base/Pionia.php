<?php

namespace Pionia;


use Psr\Container\ContainerInterface;

class Pionia
{
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    
}
