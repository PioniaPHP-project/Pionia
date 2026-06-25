<?php

namespace Pionia\Documentation\Contracts;

readonly class ActionDoc
{
    /**
     * @param array<string, array{type: string, description: string}> $params
     * @param list<string> $permissions
     */
    public function __construct(
        public string $name,
        public string $methodName,
        public ?string $summary = null,
        public ?string $auth = null,
        public array $permissions = [],
        public array $params = [],
        public ?string $returnShape = null,
        public ?string $example = null,
        public bool $deprecated = false,
    ) {
    }
}
