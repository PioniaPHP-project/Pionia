<?php

namespace Pionia\Http\Routing\Guards;

use Pionia\Collections\Arrayable;

interface RouteGuardInterface
{
    function authenticated(): static;
    function perms(array $perms): static;
    function offline(): static;
    function inMaintenanceMode(): static;
    function postOnly(): static;
    function getOnly(): static;

    function get(): Arrayable;
}
