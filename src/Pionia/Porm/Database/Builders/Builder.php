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

namespace Pionia\Porm\Database\Builders;


use Pionia\Porm\Core\ContractBuilder;
use Pionia\Porm\Core\Piql;
use Pionia\Porm\Database\Aggregation\AggregateTrait;
use Pionia\Porm\Database\Utils\FilterTrait;
use Pionia\Porm\Database\Utils\ParseTrait;

class Builder extends ContractBuilder
{
    private string $table;

    private Piql $database;

    private bool $preventLimit = false;

    private array $where = [];
    /**
     * @var mixed|string
     */
    private string|array $columns = "*";

    use AggregateTrait;
    use ParseTrait;
    use FilterTrait;

    public function __construct($table, Piql $database, $columns = "*", $where = [])
    {
        $this->table = $table;
        $this->database = $database;
        $this->columns = $columns;
        $this->where = $where;
    }

    /**
     * Returns one item from the CDatabase
     * @param array|int|null $where
     * @return object|null
     *
     * @example ```php
     * $row = Table::from('user')
     *      ->filter(['last_name' => 'Ezra'])
     *      ->get();
     * ```
     */
    public function get(array|int|null $where = null): ?object
    {
        if (is_array($where)) {
            $this->where = array_merge($this->where, ['AND' => $where]);
        } elseif (is_int($where)) {
            $this->where = array_merge($this->where, ['LIMIT' => [$where, 1]]);
        }
        $result = $this->runGet();
        return (object)$result;
    }

    /**
     * Same as get() but returns the first item of the resultset
     */
    public function first(): ?object
    {
        return $this->get(0);
    }

    public function match($columns, $keyword, $mode = 'natural'): static
    {
        $this->where['MATCH'] = ['columns' => $columns, 'keyword' => $keyword, 'mode' => $mode];
        return $this;
    }

    public function build(): mixed
    {
        return $this;
    }
}
