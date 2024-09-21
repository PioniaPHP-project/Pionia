<?php

namespace Pionia\Collections;

class HighOrderWhenProxy
{
    /**
     * The target being conditionally operated on.
     *
     * @var mixed
     */
    protected mixed $target;

    /**
     * The condition for proxying.
     *
     * @var bool
     */
    protected bool $condition;

    /**
     * Indicates whether the proxy has a condition.
     *
     * @var bool
     */
    protected bool $hasCondition = false;

    /**
     * Determine whether the condition should be negated.
     *
     * @var bool
     */
    protected bool $negateConditionOnCapture;

    /**
     * Create a new proxy instance.
     *
     * @param  mixed  $target
     * @return void
     */
    public function __construct(mixed $target)
    {
        $this->target = $target;
    }

    /**
     * Set the condition on the proxy.
     *
     * @param bool $condition
     * @return $this
     */
    public function condition(bool $condition): static
    {
        [$this->condition, $this->hasCondition] = [$condition, true];

        return $this;
    }

    /**
     * Indicate that the condition should be negated.
     *
     * @return $this
     */
    public function negateConditionOnCapture(): static
    {
        $this->negateConditionOnCapture = true;

        return $this;
    }

    /**
     * Proxy accessing an attribute onto the target.
     *
     * @param string $key
     * @return mixed
     */
    public function __get(string $key)
    {
        if (! $this->hasCondition) {
            $condition = $this->target->{$key};

            return $this->condition($this->negateConditionOnCapture ? ! $condition : $condition);
        }

        return $this->condition
            ? $this->target->{$key}
            : $this->target;
    }

    /**
     * Proxy a method call on the target.
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        if (! $this->hasCondition) {
            $condition = $this->target->{$method}(...$parameters);

            return $this->condition($this->negateConditionOnCapture ? ! $condition : $condition);
        }

        return $this->condition
            ? $this->target->{$method}(...$parameters)
            : $this->target;
    }
}
