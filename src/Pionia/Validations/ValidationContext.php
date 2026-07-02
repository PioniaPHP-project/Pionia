<?php

namespace Pionia\Validations;

use Pionia\Collections\Arrayable;
use Pionia\Exceptions\ValidationException;

/**
 * Context passed to custom validation rules.
 */
final class ValidationContext
{
    public function __construct(
        public readonly string $field,
        public readonly ?string $parameter,
        public readonly Arrayable $data,
        public readonly Validator $validator,
    ) {}

    public function value(): mixed
    {
        return $this->data->get($this->field);
    }

    /**
     * @throws ValidationException
     */
    public function fail(string $message): never
    {
        throw new ValidationException($message);
    }
}
