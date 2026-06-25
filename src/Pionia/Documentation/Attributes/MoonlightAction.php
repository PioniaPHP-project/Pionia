<?php

namespace Pionia\Documentation\Attributes;

#[\Attribute(\Attribute::TARGET_METHOD)]
readonly class MoonlightAction
{
    /**
     * @param list<string> $permissions
     */
    public function __construct(
        public string $name,
        public ?string $summary = null,
        public ?string $auth = null,
        public array $permissions = [],
    ) {
    }
}
