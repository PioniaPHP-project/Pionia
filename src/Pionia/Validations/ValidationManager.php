<?php

namespace Pionia\Validations;

use Pionia\Collections\Arrayable;
use Pionia\Validations\Contracts\ValidationRuleContract;

/**
 * Application-wide registry for custom validation rules.
 *
 * Register once during boot; use everywhere via rules() or validate()->rule().
 */
final class ValidationManager
{
    /** @var array<string, callable|ValidationRuleContract|class-string<ValidationRuleContract>> */
    private array $rules = [];

    /** @var array<string, ValidationRuleContract> */
    private array $instances = [];

    /**
     * @param callable(ValidationContext): void|ValidationRuleContract|class-string<ValidationRuleContract> $rule
     */
    public function extend(string $name, callable|ValidationRuleContract|string $rule): static
    {
        $name = $this->normalizeName($name);

        if (is_string($rule) && !is_subclass_of($rule, ValidationRuleContract::class)) {
            throw new \InvalidArgumentException(
                'Custom validation rule classes must implement ' . ValidationRuleContract::class . '.',
            );
        }

        $this->rules[$name] = $rule;
        unset($this->instances[$name]);

        return $this;
    }

    public function has(string $name): bool
    {
        return isset($this->rules[$this->normalizeName($name)]);
    }

    /**
     * @return list<string>
     */
    public function registeredRuleNames(): array
    {
        return array_values(array_keys($this->rules));
    }

    /**
     * @throws \Pionia\Exceptions\ValidationException
     */
    public function apply(
        string $name,
        Validator $validator,
        string $field,
        ?string $parameter,
        Arrayable $data,
    ): void {
        $name = $this->normalizeName($name);

        if (!isset($this->rules[$name])) {
            throw new \Pionia\Exceptions\ValidationException("Unknown validation rule [{$name}] on field [{$field}].");
        }

        $context = new ValidationContext($field, $parameter, $data, $validator);
        $rule = $this->resolveRule($name);

        if ($rule instanceof ValidationRuleContract) {
            $rule->validate($context);

            return;
        }

        $rule($context);
    }

  /**
   * @return callable(ValidationContext): void|ValidationRuleContract
   */
    private function resolveRule(string $name): callable|ValidationRuleContract
    {
        $rule = $this->rules[$name];

        if (is_string($rule)) {
            return $this->instances[$name] ??= new $rule();
        }

        return $rule;
    }

    private function normalizeName(string $name): string
    {
        return strtolower(trim($name));
    }
}
