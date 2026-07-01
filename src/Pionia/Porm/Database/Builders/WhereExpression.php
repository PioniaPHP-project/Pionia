<?php

namespace Pionia\Porm\Database\Builders;

use InvalidArgumentException;

/**
 * Translates ORM-style where(column, operator, value) calls into Piql WHERE arrays.
 */
final class WhereExpression
{
    /** @var array<string, string> */
    private const ALIASES = [
        '=' => 'equals',
        'equals' => 'equals',
        'equal' => 'equals',
        'eq' => 'equals',
        'is' => 'equals',

        '!=' => 'not_equal',
        'not_equal' => 'not_equal',
        'not_equals' => 'not_equal',
        'not' => 'not_equal',
        'neq' => 'not_equal',
        'is_not' => 'not_equal',

        '>' => 'greater_than',
        'greater_than' => 'greater_than',
        'gt' => 'greater_than',

        '>=' => 'greater_than_or_equal',
        'greater_than_or_equal' => 'greater_than_or_equal',
        'gte' => 'greater_than_or_equal',

        '<' => 'less_than',
        'less_than' => 'less_than',
        'lt' => 'less_than',

        '<=' => 'less_than_or_equal',
        'less_than_or_equal' => 'less_than_or_equal',
        'lte' => 'less_than_or_equal',

        'includes' => 'includes',
        'include' => 'includes',
        'contains' => 'includes',
        'like' => 'includes',

        'not_includes' => 'not_includes',
        'not_include' => 'not_includes',
        'not_contains' => 'not_includes',
        'not_like' => 'not_includes',

        'starts_with' => 'starts_with',
        'start_with' => 'starts_with',
        'begins_with' => 'starts_with',

        'ends_with' => 'ends_with',
        'end_with' => 'ends_with',

        'in' => 'in',
        'not_in' => 'not_in',

        'between' => 'between',
        'not_between' => 'not_between',

        'is_null' => 'is_null',
        'null' => 'is_null',

        'is_not_null' => 'is_not_null',
        'not_null' => 'is_not_null',

        'regexp' => 'regexp',
        'regex' => 'regexp',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function compile(string $column, mixed $operatorOrValue, mixed $value = null): array
    {
        if ($value === null && !self::recognizesOperator($operatorOrValue)) {
            return [$column => $operatorOrValue];
        }

        if ($value === null && self::isNullaryOperator($operatorOrValue)) {
            return match (self::normalizeOperator($operatorOrValue)) {
                'is_null' => [$column => null],
                'is_not_null' => [$column . '[!]' => null],
                default => throw new InvalidArgumentException(
                    sprintf('where(%s, %s) is missing a value.', $column, self::stringify($operatorOrValue))
                ),
            };
        }

        if ($value === null) {
            throw new InvalidArgumentException(
                sprintf('where(%s, %s) is missing a value.', $column, self::stringify($operatorOrValue))
            );
        }

        $operator = self::normalizeOperator($operatorOrValue);

        return match ($operator) {
            'equals' => [$column => $value],
            'not_equal' => [$column . '[!]' => $value],
            'greater_than' => [$column . '[>]' => $value],
            'greater_than_or_equal' => [$column . '[>=]' => $value],
            'less_than' => [$column . '[<]' => $value],
            'less_than_or_equal' => [$column . '[<=]' => $value],
            'includes' => [$column . '[~]' => $value],
            'not_includes' => [$column . '[!~]' => $value],
            'starts_with' => [$column . '[~]' => self::likePrefix($value)],
            'ends_with' => [$column . '[~]' => self::likeSuffix($value)],
            'in' => self::compileIn($column, $value, false),
            'not_in' => self::compileIn($column, $value, true),
            'between' => self::compileBetween($column, $value, false),
            'not_between' => self::compileBetween($column, $value, true),
            'is_null' => [$column => null],
            'is_not_null' => [$column . '[!]' => null],
            'regexp' => [$column . '[REGEXP]' => $value],
            default => throw new InvalidArgumentException("Unsupported where operator [{$operator}]."),
        };
    }

    public static function recognizesOperator(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $key = self::normalizeKey($value);

        return isset(self::ALIASES[$key]);
    }

    private static function isNullaryOperator(mixed $value): bool
    {
        if (!self::recognizesOperator($value)) {
            return false;
        }

        return in_array(self::normalizeOperator($value), ['is_null', 'is_not_null'], true);
    }

    private static function normalizeOperator(mixed $operator): string
    {
        if (!is_string($operator)) {
            throw new InvalidArgumentException('Where operator must be a string.');
        }

        $key = self::normalizeKey($operator);

        if (!isset(self::ALIASES[$key])) {
            throw new InvalidArgumentException("Unknown where operator [{$operator}].");
        }

        return self::ALIASES[$key];
    }

    private static function normalizeKey(string $operator): string
    {
        return strtolower(str_replace('-', '_', trim($operator)));
    }

  /**
     * @return array<string, mixed>
     */
    private static function compileIn(string $column, mixed $value, bool $negated): array
    {
        if (!is_array($value)) {
            throw new InvalidArgumentException('in / not_in expects an array of values.');
        }

        return $negated
            ? [$column . '[!]' => array_values($value)]
            : [$column => array_values($value)];
    }

    /**
     * @return array<string, mixed>
     */
    private static function compileBetween(string $column, mixed $value, bool $negated): array
    {
        if (!is_array($value) || count($value) !== 2) {
            throw new InvalidArgumentException('between / not_between expects a two-element array.');
        }

        return [$column . ($negated ? '[><]' : '[<>]') => array_values($value)];
    }

    private static function likePrefix(mixed $value): string
    {
        $string = strval($value);

        return str_ends_with($string, '%') ? $string : $string . '%';
    }

    private static function likeSuffix(mixed $value): string
    {
        $string = strval($value);

        return str_starts_with($string, '%') ? $string : '%' . $string;
    }

    private static function stringify(mixed $value): string
    {
        return is_string($value) ? $value : gettype($value);
    }
}
