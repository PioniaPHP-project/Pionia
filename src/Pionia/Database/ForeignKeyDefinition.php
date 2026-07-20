<?php

namespace Pionia\Database;

/**
 * Fluent foreign-key constraint definition attached to a {@see Blueprint}.
 */
final class ForeignKeyDefinition
{
    private string $references = 'id';

    private ?string $on = null;

    private ?string $onDelete = null;

    private ?string $onUpdate = null;

    private ?string $name = null;

    /**
     * @param list<string> $columns
     */
    public function __construct(
        private readonly Blueprint $blueprint,
        private readonly array $columns,
    ) {
    }

    public function references(string $column): self
    {
        $this->references = $column;

        return $this;
    }

    public function on(string $table): self
    {
        $this->on = $table;

        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function cascadeOnDelete(): self
    {
        $this->onDelete = 'cascade';

        return $this;
    }

    public function cascadeOnUpdate(): self
    {
        $this->onUpdate = 'cascade';

        return $this;
    }

    public function nullOnDelete(): self
    {
        $this->onDelete = 'set null';

        return $this;
    }

    public function restrictOnDelete(): self
    {
        $this->onDelete = 'restrict';

        return $this;
    }

    public function restrictOnUpdate(): self
    {
        $this->onUpdate = 'restrict';

        return $this;
    }

    public function noActionOnDelete(): self
    {
        $this->onDelete = 'no action';

        return $this;
    }

    /** @return list<string> */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function getReferences(): string
    {
        return $this->references;
    }

    public function getOn(): ?string
    {
        return $this->on;
    }

    public function getOnDelete(): ?string
    {
        return $this->onDelete;
    }

    public function getOnUpdate(): ?string
    {
        return $this->onUpdate;
    }

    public function getName(): ?string
    {
        return $this->name;
    }
}
