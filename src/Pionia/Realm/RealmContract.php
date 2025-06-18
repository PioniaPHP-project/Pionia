<?php

namespace Pionia\Realm;

interface RealmContract
{
    function boot(): static;
    function addBootingProvider(callable $callable): static;
    function addBootedProvider(callable $callable): static;
}
