<?php

namespace Pionia\Porm\Core;

abstract class ContractBuilder
{
    public static function builder(...$args): static
    {
        return new static(...$args);
    }

    abstract public function build(): mixed;
}
