<?php

/**
 * PORM - CDatabase querying tool for pionia framework.
 *
 * This package can be used as is or with the Pionia Framework. Anyone can reproduce and update this as they see fit.
 *
 * @copyright 2024,  Pionia Project - Jet Ezra
 *
 * @author Jet Ezra
 * @version 1.0.0
 * @link https://pionia.netlify.app/
 * @license https://opensource.org/licenses/MIT
 *
 **/

namespace Pionia\Porm\Database\Utils;

trait JoinParseTrait
{

    private function runSelect(?callable $callback): ?array
    {
        return $this->database->select($this->table, $this->joins, $this->columns, $this->where, $callback);
    }

    /**
     * Returns all items from the CDatabase. If a callback is passed, it will be called on each item in the resultset
     *
     * @example ```php
     * // Assignment method
     * $row = Table::from('user')
     *     ->filter(['last_name' => 'Ezra'])
     *    ->all();
     *
     * // Callback method- this is little bit faster than the assignment method
     * Table::from('user')
     *    ->filter(['last_name' => 'Ezra'])
     *   ->all(function($row) {
     *      echo $row->first_name;
     *  });
     * ```
     * @param callable|null $callback This is the receiver for the current resultset
     * @return array|null
     */
    public function all(?callable $callback = null): ?array
    {
        return $this->runSelect($callback);
    }

    public function where(array $where): static
    {
        $this->where = array_merge($this->where, $where);
        return $this;
    }

    private function join(string $joinTable, string $alias, string|array $on_or_using, string $joinType,): static
    {
        $joinTable = $joinTable . "(" . $alias . ")";
        $finalJoinTable = $joinType . $joinTable;
        $this->joins[$finalJoinTable] = $on_or_using;
        return $this;
    }

    public function inner($table, string|array $on_or_using, ?string $alias = null): static
    {
        if (!$alias) {
            $alias = $table;
        }
        return $this->join($table, $alias, $on_or_using, "[><]");
    }

    public function left($table, string|array $on_or_using, ?string $alias = null): static
    {
        if (!$alias) {
            $alias = $table;
        }
        return $this->join($table, $alias, $on_or_using, "[>]");
    }

    public function right($table, string|array $on_or_using, ?string $alias = null): static
    {
        if (!$alias) {
            $alias = $table;
        }
        return $this->join($table, $alias, $on_or_using, "[<]");
    }

    public function full($table, string|array $on_or_using, ?string $alias = null): static
    {
        if (!$alias) {
            $alias = $table;
        }
        return $this->join($table, $alias, $on_or_using, "[<>]");
    }
}
