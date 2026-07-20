<?php

namespace Pionia\Auth\Attributes;

/**
 * Require any one of the listed permissions (OR).
 *
 * Usable on a service class (with optional `$except`) or an action method.
 * Implies authentication.
 *
 * @example
 * #[CanAny(['task.edit', 'task.admin'])]
 * protected function patchAction(Arrayable $data): ApiResponse { … }
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class CanAny
{
    /**
     * @param list<string>|string $permission Permissions — any one is enough
     * @param list<string> $except Exempt actions when used on a service class
     */
    public function __construct(
        public string|array $permission,
        public ?string $message = null,
        public array $except = [],
    ) {
    }
}
