<?php

namespace Pionia\Auth\Attributes;

/**
 * Require an authenticated user for a service or action.
 *
 * On a **service class**, every action needs auth unless listed in `$except`
 * (request action name, method name, or snake_case base — e.g. `login`, `loginAction`).
 *
 * On an **action method**, that action alone requires auth.
 *
 * Enforced in {@see \Pionia\Http\Services\AbstractService::processAction()} before the action body.
 *
 * @example
 * #[Authenticated(except: ['login', 'register'])]
 * class MemberService extends Service { … }
 *
 * @example
 * #[Authenticated]
 * protected function createAction(Arrayable $data): ApiResponse { … }
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
readonly class Authenticated
{
    /**
     * @param list<string> $except Action names exempt from service-level auth
     */
    public function __construct(
        public ?string $message = null,
        public array $except = [],
    ) {
    }
}
