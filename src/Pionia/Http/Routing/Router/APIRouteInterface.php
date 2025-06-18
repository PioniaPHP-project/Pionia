<?php

namespace Pionia\Http\Routing\Router;

interface APIRouteInterface
{
    static function to(string $switch, ?string $version = null): static ;
}
