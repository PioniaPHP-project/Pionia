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

use Exception;

trait FilterTrait
{
    /**
     * @throws Exception
     */
    public function limit(int $limit): static
    {
        if ($this->preventLimit) {
            throw new Exception('You cannot use limit more than once in the same query. You may think about using arrays for LIMITS instead');
        }
        $this->preventLimit = true;

        // we probably have a limit already set the limit from the offset
        if (isset($this->where['LIMIT'])) {
            // if the limit is an array, we will just set the limit part to the above
            if (is_array($this->where['LIMIT'])) {
                $limit_value = $this->where['LIMIT'][1];
                $offset_value = $this->where['LIMIT'][0];
                $this->where['LIMIT'] = [$offset_value, $limit_value];
                return $this;
            }
        }

        $this->where['LIMIT'] = $limit;
        return $this;
    }

    public function startAt(int $startPoint = 0): static
    {
        if (isset($this->where['LIMIT'])) {
            if (is_array($this->where['LIMIT'])) {
                $limit_value = $this->where['LIMIT'][1];
                $this->where['LIMIT'] = [$startPoint, $limit_value];
            } else {
                // if we have a limit set, we will set the limit to the start point
                $this->where['LIMIT'] = [$startPoint, $this->where['LIMIT']];
            }
        } else {
            // if we did not set a limit, we will set a limit of 100000000, which is a very large number to ensure that we get all the records
            $this->where['LIMIT'] = [$startPoint, 100000000];
        }
        return $this;
    }

    public function group(string|array $group)
    {
        $this->where['GROUP'] = $group;
        return $this;
    }

    /**
     * Adds a 'having' clause to the query
     * @param string $column
     * @param mixed $value
     * @param ?string $needle can be >, <, !,>=, <=.  >< and <> are available for datetime
     * @return $this
     */
    public function having(string $column, mixed $value, ?string $needle = null): static
    {
        if ($needle) {
            $column .= "[$needle]";
        }
        if (isset($this->where['HAVING'])) {
            $havingArray = $this->where['HAVING'];
            $havingArray = array_merge($havingArray, [$column => $value]);
            $this->where = array_merge($this->where, $havingArray);
        } else {
            $this->where = array_merge($this->where, ['HAVING' => [$column . "[>]" => $value]]);
        }

        return $this;
    }

    /**
     * Orders the query by certain value.
     *
     * @example ```php
     * // single column
     * ->filter()
     * ->orderBy("name")
     *
     * // multiple Order items
     * ->filter()
     * ->orderBy(['name' => 'DESC', 'age' => 'ASC'])
     * @param string|array $value
     * @return $this
     */
    public function orderBy(string|array $value): static
    {
        if (isset($this->where['ORDER'])) {
            $orderArray = $this->where['ORDER'];
            if (is_array($orderArray)) {
                $orderArray[] = $value;
                $this->where = array_merge($this->where, $orderArray);
            } else {
                $this->where["ORDER"] = $value;
            }
        } else {
            $this->where['ORDER'] = $value;
        }

        return $this;
    }
}
