<?php

namespace Pionia\Database;

/**
 * Fluent column modifiers for {@see Blueprint}.
 *
 * Methods return `$this` so callers can chain `nullable()`, `default()`, `unique()`, etc.
 */
final class ColumnDefinition
{
    private bool $nullable = false;

    private mixed $default = null;

    private bool $hasDefault = false;

    private bool $unique = false;

    private bool $index = false;

    private bool $primary = false;

    private bool $autoIncrement = false;

    private bool $unsigned = false;

    private ?string $comment = null;

    private ?string $after = null;

    private bool $change = false;

    private bool $useCurrent = false;

    private bool $useCurrentOnUpdate = false;

    /** @var list<string>|null */
    private ?array $allowed = null;

    private int $length = 255;

    private int $precision = 8;

    private int $scale = 2;

    private int $total = 8;

    private int $places = 2;

    public function __construct(
        private readonly Blueprint $blueprint,
        private readonly string $type,
        private readonly string $name,
        array $parameters = [],
    ) {
        if (isset($parameters['length'])) {
            $this->length = (int) $parameters['length'];
        }
        if (isset($parameters['precision'])) {
            $this->precision = (int) $parameters['precision'];
        }
        if (isset($parameters['scale'])) {
            $this->scale = (int) $parameters['scale'];
        }
        if (isset($parameters['total'])) {
            $this->total = (int) $parameters['total'];
        }
        if (isset($parameters['places'])) {
            $this->places = (int) $parameters['places'];
        }
        if (isset($parameters['allowed']) && is_array($parameters['allowed'])) {
            $this->allowed = array_values($parameters['allowed']);
        }
        if (!empty($parameters['autoIncrement'])) {
            $this->autoIncrement = true;
        }
        if (!empty($parameters['unsigned'])) {
            $this->unsigned = true;
        }
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    public function unique(bool $value = true): self
    {
        $this->unique = $value;

        return $this;
    }

    public function index(bool $value = true): self
    {
        $this->index = $value;

        return $this;
    }

    public function primary(bool $value = true): self
    {
        $this->primary = $value;

        return $this;
    }

    public function autoIncrement(bool $value = true): self
    {
        $this->autoIncrement = $value;

        return $this;
    }

    public function unsigned(bool $value = true): self
    {
        $this->unsigned = $value;

        return $this;
    }

    public function comment(string $text): self
    {
        $this->comment = $text;

        return $this;
    }

    public function after(string $column): self
    {
        $this->after = $column;

        return $this;
    }

    public function change(): self
    {
        $this->change = true;

        return $this;
    }

    public function useCurrent(): self
    {
        $this->useCurrent = true;

        return $this;
    }

    public function useCurrentOnUpdate(): self
    {
        $this->useCurrentOnUpdate = true;

        return $this;
    }

    /**
     * Infer referenced table from column name (`user_id` → `users`) when `$table` is null.
     */
    public function constrained(?string $table = null, string $column = 'id'): ForeignKeyDefinition
    {
        if ($table === null) {
            $base = str_ends_with($this->name, '_id')
                ? substr($this->name, 0, -3)
                : $this->name;
            $table = $this->pluralize($base);
        }

        return $this->blueprint->foreign($this->name)
            ->references($column)
            ->on($table);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isUnique(): bool
    {
        return $this->unique;
    }

    public function isIndex(): bool
    {
        return $this->index;
    }

    public function isPrimary(): bool
    {
        return $this->primary;
    }

    public function isAutoIncrement(): bool
    {
        return $this->autoIncrement;
    }

    public function isUnsigned(): bool
    {
        return $this->unsigned;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function getAfter(): ?string
    {
        return $this->after;
    }

    public function isChange(): bool
    {
        return $this->change;
    }

    public function usesCurrent(): bool
    {
        return $this->useCurrent;
    }

    public function usesCurrentOnUpdate(): bool
    {
        return $this->useCurrentOnUpdate;
    }

    /** @return list<string>|null */
    public function getAllowed(): ?array
    {
        return $this->allowed;
    }

    public function getLength(): int
    {
        return $this->length;
    }

    public function getPrecision(): int
    {
        return $this->precision;
    }

    public function getScale(): int
    {
        return $this->scale;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getPlaces(): int
    {
        return $this->places;
    }

    private function pluralize(string $word): string
    {
        if (function_exists('plural')) {
            return (string) plural($word);
        }

        if (str_ends_with($word, 'y') && !preg_match('/[aeiou]y$/i', $word)) {
            return substr($word, 0, -1) . 'ies';
        }

        if (preg_match('/(s|x|z|ch|sh)$/i', $word)) {
            return $word . 'es';
        }

        return $word . 's';
    }
}
