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

use PDOStatement;

trait ParseTrait
{

    private function runSelect(?callable $callback): ?array
    {
        if (!$callback) {
            $result = $this->database->select($this->table, $this->columns, $this->where);
        } else {
            $result = $this->database->select($this->table, $this->columns, $this->where, $callback);
        }

        return $result;
    }

    private function runGet(): mixed
    {
        return $this->database->get($this->table, $this->columns, $this->where);
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

    /**
     * @param int|array|string $where
     * @param string|null $idField
     * @return PDOStatement|null
     * @example ```php
     *   $res1 = Porm::from('users')->delete(1); // deletes a user with id 1
     *   $res2 = Porm::from('users')->delete(['name' => 'John']); // deletes a user with name John
     * ```
     */
    public function delete(int|array|string $where, ?string $idField = 'id'): ?PDOStatement
    {
        if (!is_array($where)) {
            $where = [$idField => $where];
        }
        $this->where = array_merge($this->where, $where);
        return $this->database->delete($this->table, $this->where);
    }
}
