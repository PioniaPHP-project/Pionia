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
use Pionia\Porm\Database\Utils\FilterTrait;
use Pionia\Porm\Database\Utils\JoinParseTrait;

/**
 * Builds relationships across multiple tables.
 */
class Join extends ContractBuilder
{
    private string $table;

    private Piql $database;

    private bool $preventLimit = false;

    private array $where;
    /**
     * @var mixed|string
     */
    private string|array $columns;

    /**
     * List of all joins that are about to be applied.These are in a medoo format.
     * @var array
     */
    private array $joins = [];

    use JoinParseTrait;
    use FilterTrait;

    public function __construct($table, Piql $database, $columns = "*", $where = [])
    {
        $this->table = $table;
        $this->database = $database;
        $this->columns = $columns;
        $this->where = $where;
    }

    /**
     * Returns the medoo-like join array that was generated after chaining multiple joins
     * @return array
     */
    public function getJoins(): array
    {
        return $this->joins;
    }

    public function build(): Join
    {
        return $this;
    }

    /**
     * @param string|null $column
     * @param array|null $where
     * @return int|null
     * @see Core::count()
     */
    public function count(?string $column = "*", ?array $where = null): ?int
    {
        if (is_array($where)) {
            $this->where = array_merge($this->where, $where);
        }
        return $this->database->count($this->table, $this->joins, $column, $this->where);
    }
}
