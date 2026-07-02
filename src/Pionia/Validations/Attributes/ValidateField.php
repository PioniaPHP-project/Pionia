<?php

namespace Pionia\Validations\Attributes;

/**
 * Single-field validation rule for a Moonlight action (repeatable).
 *
 * @example
 * #[ValidateField('email', 'required|email')]
 * #[ValidateField('password', 'required|password|min:8')]
 * protected function registerAction(Arrayable $data): ApiResponse
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class ValidateField
{
    public function __construct(
        public string $field,
        public string $rules,
    ) {
    }
}
