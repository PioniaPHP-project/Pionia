<?php

namespace Pionia\Validations;

use Pionia\Collections\Arrayable;

class Validator
{
    use ValidationTrait;

    protected string $hook;

    protected ?Arrayable $hayStack;

    public static function validate(string $keyToValidate, ?Arrayable $data): static
    {
        $klass = new static();
        $klass->hook = $keyToValidate;
        $klass->hayStack = $data;
        return $klass;
    }

}
