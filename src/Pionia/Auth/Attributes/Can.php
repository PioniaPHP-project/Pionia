<?php

namespace Pionia\Auth\Attributes;

/**
 * Require one permission (string) or all listed permissions (array).
 *
 * Usable on a service class (with optional `$except`) or an action method.
 * Implies authentication.
 *
 * @example
 * #[Can('task.create')]
 * #[Can(['task.update', 'task.admin'])]
 * protected function updateAction(Arrayable $data): ApiResponse { … }
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
readonly class Can
{
    /**
     * @param string|list<string> $permission Single permission or list (all required)
     * @param list<string> $except Exempt actions when used on a service class
     */
    public function __construct(
        public string|array $permission,
        public ?string $message = null,
        public array $except = [],
    ) {
    }
}
