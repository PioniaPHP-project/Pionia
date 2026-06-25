<?php

namespace Pionia\Documentation\Contracts;

readonly class MoonlightApiCatalog
{
    /**
     * @param array<string, array<string, ServiceDoc>> $versions version → service alias → ServiceDoc
     */
    public function __construct(
        public string $title,
        public array $versions,
    ) {
    }
}
