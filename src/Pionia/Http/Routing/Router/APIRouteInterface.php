<?php

namespace Pionia\Http\Routing;

interface APIRouteInterface
{
    static function to(string $switch, ?string $version = null): static ;
}
