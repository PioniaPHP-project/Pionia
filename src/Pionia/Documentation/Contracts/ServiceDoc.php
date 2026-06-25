<?php

namespace Pionia\Documentation\Contracts;

readonly class ServiceDoc
{
    /**
     * @param list<ActionDoc> $actions
     */
    public function __construct(
        public string $version,
        public string $alias,
        public string $className,
        public ?string $summary = null,
        public ?string $auth = null,
        public ?string $table = null,
        public array $actions = [],
    ) {
    }
}
