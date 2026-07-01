<?php

namespace Pionia\Porm\Database\Builders;

/**
 * Helpers for Porm join ON / USING clauses (Medoo/Piql format).
 *
 * @example
 * ```php
 * table('orders', 'o')->join()
 *     ->left('users', JoinOn::map('user_id', 'id'), 'u')
 *     ->all();
 *
 * table('sessions')->join()
 *     ->inner('users', JoinOn::using('user_id'))
 *     ->all();
 * ```
 */
final class JoinOn
{
    /**
     * ON base_table.{baseColumn} = joined.{joinedColumn}
     *
     * Pass as the second argument to inner()/left()/right()/full().
     */
    public static function map(string $baseColumn, string $joinedColumn): array
    {
        return [$baseColumn => $joinedColumn];
    }

    /**
     * Multiple ON equalities (AND).
     *
     * @param array<string, string> $pairs
     */
    public static function maps(array $pairs): array
    {
        return $pairs;
    }

    /**
     * USING (column) — string for one column, list for several.
     */
    public static function using(string|array $columns): string|array
    {
        return $columns;
    }

    /**
     * Raw SQL ON fragment, e.g. "o.user_id = u.id AND u.active = 1".
     */
    public static function expression(string $sql): string
    {
        return $sql;
    }

    /**
     * Shorthand for a single equality expression string.
     */
    public static function columns(string $left, string $right): string
    {
        return "{$left} = {$right}";
    }
}
