<?php

namespace Pionia\Validations\Attributes;

/**
 * Declarative validation rules for a Moonlight action method.
 *
 * Runs automatically before the action body via {@see \Pionia\Http\Services\AbstractService::processAction()}.
 *
 * @example
 * #[Validated(rules: ['email' => 'required|email', 'password' => 'required|password|min:8'])]
 * protected function registerAction(Arrayable $data): ApiResponse
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
readonly class Validated
{
    /**
     * @param array<string, string|list<string>> $rules
     */
    public function __construct(public array $rules = [])
    {
    }
}
