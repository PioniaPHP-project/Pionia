<?php

namespace Pionia\Database;

/**
 * Index / unique / primary key definition on a {@see Blueprint}.
 */
final class IndexDefinition
{
    /**
     * @param list<string> $columns
     * @param 'index'|'unique'|'primary' $type
     */
    public function __construct(
        private readonly array $columns,
        private readonly string $type,
        private readonly ?string $name = null,
    ) {
    }

    /** @return list<string> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
