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

namespace Pionia\Porm\Database\Aggregation;

use Exception;
use Pionia\Porm\Core\ContractBuilder;
use Pionia\Porm\Core\Piql;

/**
 * Aggregate functions for the PORM library.
 *
 * These methods are used to perform aggregate functions on the CDatabase.
 * @link https://medoo.in/api/aggregate
 *
 * They can be used in the following way:
 * @example
 * ```php
 * $dt = Porm::from('qa_criteria')->get(Agg::gt('id', 1)); // get all records where id is greater than 1
 * var_dump($dt);
 *
 * $dt = Porm::from('qa_criteria')->get(Agg::avg('id')); // get the average of the id column
 *  var_dump($dt);
 * ```
 */
class Agg extends ContractBuilder
{
    private array $aggregated = [];

    public function build(): array
    {
        return $this->aggregated;
    }

    /**
     * Assign a random value to a column
     * @param string $columnName will be the alias of the result
     * @return Agg
     */
    public function random(string $columnName): Agg
    {
        $arg = [$columnName => Piql::raw("RAND()")];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Get the sum of a column and assign it to columnName
     * @param string $columName will be the alias of the result
     * @param string $column will be the column to get the minimum value from
     * @return Agg
     */
    public function sum(string $columName, string $column): Agg
    {
        $arg = [$columName => Piql::raw("SUM(<$column>)")];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Get the average value of a column and assing it to columnName
     * @param string $columName
     * @param string $column will be the column to get the minimum value from
     * @return Agg
     */
    public function avg(string $columName, string $column): Agg
    {
        $arg = [$columName => Piql::raw("AVG(<$column>)")];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Get the maximum value of a column
     * @param string $columnName will be the alias of the result
     * @param string $column will be the column to get the maximum value from
     * @return Agg
     */
    public function max(string $columnName, string $column): Agg
    {
        $arg = [$columnName => Piql::raw("MAX(<$column>)")];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Assign current timestamp to a column
     *
     * @param string $columName The column to assign the current timestamp to
     * @return Agg
     */
    public function now(string $columName): Agg
    {
        $arg = [$columName => Piql::raw("NOW()")];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * If uuid string is passed, checks if a column is matching the given uuid otherwise generates new one and assigns it to the column given
     *
     * @param string $columnName
     * @param ?string $uuidString optional
     * @return Agg
     */
    public function uuid(string $columnName, ?string $uuidString): Agg
    {
        if (!$uuidString) {
            $uuidString = Piql::raw("UUID()");
        }
        $arg = [$columnName => $uuidString];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }


    /**
     * Less than.
     * @param string $columnName
     * @param int $value
     * @return Agg
     */
    public function lt(string $columnName, int $value): Agg
    {
        $arg = [$columnName . "[<]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Less than or equal to.
     * @param string $columnName
     * @param int $value
     * @return Agg
     */
    public function lte(string $columnName, int $value): Agg
    {
        $arg = [$columnName . "[<=]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Greater than.
     * @param string $columnName
     * @param int $value
     * @return Agg
     */
    public function gt(string $columnName, int $value): Agg
    {
        $arg = [$columnName . "[>]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Greater than or equal to.
     * @param string $columnName
     * @param int $value
     * @return Agg
     */
    public function gte(string $columnName, mixed $value): Agg
    {
        $arg = [$columnName . "[>=]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Equal to.
     * @param string $columnName
     * @param mixed $value
     * @return Agg
     */
    public function eq(string $columnName, mixed $value): Agg
    {
        $arg = [$columnName => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Not equal to.
     * @param string $columnName
     * @param mixed $value
     * @return Agg
     */
    public function neq(string $columnName, mixed $value): Agg
    {
        $arg = [$columnName . "[!]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * add to the column value
     * @param string $columnName
     * @param mixed $value
     * @return Agg
     */
    public function plus(string $columnName, int $value): Agg
    {
        $arg = [$columnName . "[+]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * subtract from the column value
     * @param string $columnName
     * @param mixed $value
     * @return Agg
     */
    public function minus(string $columnName, int $value): Agg
    {
        $arg = [$columnName . "[-]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * multiply the column value
     * @param string $columnName
     * @param mixed $value
     * @return Agg
     */
    public function of(string $columnName, int $value): Agg
    {
        $arg = [$columnName . "[*]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * json encode the column value and assign it to the column
     * @param string $columnName
     * @param mixed $value
     * @return Agg
     */
    public function jsonified(string $columnName, array $value): Agg
    {
        $arg = [$columnName . "[JSON]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * divide the column value
     * @param string $columnName
     * @param mixed $value
     * @return Agg
     */
    public function div(string $columnName, int $value): Agg
    {
        $arg = [$columnName . "[/]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Used to add a like condition to a query
     *
     * @param string $columnName
     * @param mixed $value
     * @return Agg
     */
    public function like(string $columnName, string|array $value): Agg
    {
        $arg = [$columnName . "[~]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * Used to add a like condition to a query
     *
     * @param string $columnName
     * @param string|array $value
     * @return Agg
     */
    public function notLike(string $columnName, string|array $value): Agg
    {
        $arg = [$columnName . "[!~]" => $value];
        $this->aggregated = array_merge($this->aggregated, $arg);
        return $this;
    }

    /**
     * This compares two tables in the db
     * @example ```php
     *  Agg::builder()->columnsCompare('age_restriction', '=', 'age')
     * ```
     * @throws Exception
     */
    public function columnsCompare(string $column, string $comparison, string $otherColumn): static
    {
        if (in_array($comparison, ['=', '>', '<', '!='])) {
            throw new Exception("While comparing two columns, comparison must be one of '=', '>', '<', '!=, check the comparison between $column and $otherColumn");
        }
        $this->aggregated[] = $column . ' ' . $comparison . ' ' . $otherColumn;
        return $this;
    }

    /**
     * Checks if the value of the $columnName is between the given values.
     * Cool for all number formats and dates
     * @param $columnName
     * @param array $values
     * @return $this
     * @example
     */
    public function between($columnName, array $values): static
    {
        $this->aggregated = array_merge($this->aggregated, [$columnName . "[<>]" => $values]);
        return $this;
    }

    /**
     * Checks if the value of the $columnName is not between the given values.
     * Cool for all number formats and dates too
     * @param $columnName
     * @param array $values
     * @return $this
     * @example
     */
    public function notBetween($columnName, array $values): static
    {
        $this->aggregated = array_merge($this->aggregated, [$columnName . "[><]" => $values]);
        return $this;
    }

    /**
     * Check if the value of the given column matches the given regular expression
     * @param $columnName
     * @param string $regex
     * @return $this
     */
    public function regex($columnName, string $regex): static
    {
        $this->aggregated = array_merge($this->aggregated, [$columnName . '[REGEXP]' => $regex]);
        return $this;
    }
}
