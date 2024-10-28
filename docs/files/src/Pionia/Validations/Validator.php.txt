<?php

namespace Pionia\Validations;

use Pionia\Collections\Arrayable;

/**
 * This class is used to validate data
 */
class Validator
{
    use ValidationTrait;
    /**
     * The hook to validate
     */
    protected string $hook;
    /**
     * @var Arrayable|null The data to validate against
     */
    protected ?Arrayable $hayStack;

    /**
     * Get the value of hook
     * @return Validator
     */
    public function get(): mixed
    {
        return $this->hayStack->get($this->hook);
    }

    /**
     * Get the value of hook
     * @param string $hook
     * @return Validator
     */
    public function valueOf(string $hook): mixed
    {
        return $this->hayStack->get($hook);
    }

}
