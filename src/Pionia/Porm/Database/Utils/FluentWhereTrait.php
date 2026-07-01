<?php

namespace Pionia\Porm\Database\Utils;

use Pionia\Porm\Database\Builders\WhereExpression;

/**
 * ORM-style where(column, operator, value) on Porm builders.
 */
trait FluentWhereTrait
{
    /**
     * @param array<string, mixed>|string $column
     */
    public function where(array|string $column, mixed $operatorOrValue = null, mixed $value = null): static
    {
        if (is_array($column)) {
            $this->where = array_merge($this->where, $column);

            return $this;
        }

        $this->mergeWhere(WhereExpression::compile($column, $operatorOrValue, $value));

        return $this;
    }

  /**
     * @param array<string, mixed>|string $column
     */
    public function orWhere(array|string $column, mixed $operatorOrValue = null, mixed $value = null): static
    {
        if (is_array($column)) {
            $this->mergeOrWhere($column);

            return $this;
        }

        $this->mergeOrWhere(WhereExpression::compile($column, $operatorOrValue, $value));

        return $this;
    }

    public function whereEquals(string $column, mixed $value): static
    {
        return $this->where($column, $value);
    }

    public function whereIs(string $column, mixed $value): static
    {
        return $this->where($column, 'is', $value);
    }

    public function whereNotEqual(string $column, mixed $value): static
    {
        return $this->where($column, 'not_equal', $value);
    }

    public function whereStartsWith(string $column, string $value): static
    {
        return $this->where($column, 'starts_with', $value);
    }

    public function whereEndsWith(string $column, string $value): static
    {
        return $this->where($column, 'ends_with', $value);
    }

    public function whereIncludes(string $column, string $value): static
    {
        return $this->where($column, 'includes', $value);
    }

    public function whereNotIncludes(string $column, string $value): static
    {
        return $this->where($column, 'not_includes', $value);
    }

    public function whereGreaterThan(string $column, mixed $value): static
    {
        return $this->where($column, '>', $value);
    }

    public function whereGreaterThanOrEqual(string $column, mixed $value): static
    {
        return $this->where($column, '>=', $value);
    }

    public function whereLessThan(string $column, mixed $value): static
    {
        return $this->where($column, '<', $value);
    }

    public function whereLessThanOrEqual(string $column, mixed $value): static
    {
        return $this->where($column, '<=', $value);
    }

    public function whereIn(string $column, array $values): static
    {
        return $this->where($column, 'in', $values);
    }

    public function whereNotIn(string $column, array $values): static
    {
        return $this->where($column, 'not_in', $values);
    }

    public function whereNull(string $column): static
    {
        return $this->where($column, 'is_null');
    }

    public function whereNotNull(string $column): static
    {
        return $this->where($column, 'is_not_null');
    }

    public function whereBetween(string $column, mixed $min, mixed $max): static
    {
        return $this->where($column, 'between', [$min, $max]);
    }

    public function whereNotBetween(string $column, mixed $min, mixed $max): static
    {
        return $this->where($column, 'not_between', [$min, $max]);
    }

    /**
     * @param array<string, mixed> $clause
     */
    private function mergeWhere(array $clause): void
    {
        $this->where = array_merge($this->where, $clause);
    }

    /**
     * @param array<string, mixed> $clause
     */
    private function mergeOrWhere(array $clause): void
    {
        $reserved = ['GROUP', 'ORDER', 'HAVING', 'LIMIT', 'LIKE', 'MATCH', 'OR', 'AND'];

        $meta = array_intersect_key($this->where, array_flip($reserved));
        $conditions = array_diff_key($this->where, array_flip($reserved));

        $branch = isset($this->where['OR']) && is_array($this->where['OR'])
            ? $this->where['OR']
            : $conditions;

        $this->where = array_merge($meta, ['OR' => array_merge($branch, $clause)]);
    }
}
