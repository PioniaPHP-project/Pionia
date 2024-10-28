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
use PDOStatement;
use Pionia\Collections\Arrayable;
use Pionia\Exceptions\DatabaseException;
use Pionia\Exceptions\NotFoundException;
use Pionia\Porm\Core\Raw;
use Pionia\Porm\Database\Aggregation\AggregateTrait;
use Pionia\Porm\Database\Builders\Builder;
use Pionia\Porm\Database\Builders\Join;

trait TableLevelQueryTrait
{
    use AggregateTrait;
    use ParseTrait;

    /**
     * This checks if the table has a record that matches the where clause
     * @param string|array|int|null $where
     * @param string|null $pkField
     * @return bool
     *
     * @throws Exception
     * @example ```php
     *      $res1 = Porm::from('users')->has(1); // with integer where clause that defaults to id = 1
     *      $res2 = Porm::from('users')->has(['id' => 1]); // with array where clause
     * ```
     */
    public function has(string|array|int|null $where, ?string $pkField = 'id'): bool
    {
        $this->checkFilterMode("You cannot check if an item exists at this point in the query, check the usage of the `has()`
         method in the query builder for " . $this->table);
        if ($this->preventHas) {
            throw new Exception('You cannot call `has()` at this point in the query, check the usage of the `has()` method in the query builder for ' . $this->table);
        }
        if (is_string($where) || is_int($where)) {
            $where = [$pkField => $where];
        }
        $this->where = array_merge($this->where, $where);
        if (count($this->where) < 1) {
            throw new Exception("You did not define any conditions for `has` method.");
        }
        return $this->database->has($this->table, $this->where);
    }

    /**
     * Fetches random n items from the table, default to 1
     *
     * @example ```php
     *     $res1 = Porm::from('users')->random(); // fetches a random user
     *     $res2 = Porm::from('users')->random(5); // fetches 5 random users
     *     $res3 = Porm::from('users')->random(5, ['last_name' => 'Pionia']); // fetches 5 random users with last name Pionia
     * ```
     * @param ?int $limit
     * @param array|null $where
     * @return array|mixed|object
     * @throws Exception
     */
    public function random(?int $limit = 1, ?array $where = null): mixed
    {
        $this->checkFilterMode("You cannot fetch random items at this point in the query, check the usage of the `random()`
         method in the query builder for " . $this->table);

        if ($where === null) {
            $where = [];
        }

        if (!isset($where['LIMIT'])) {
            $where['LIMIT'] = $limit;
        }

        $this->where = array_merge($this->where, $where);
        $result = $this->database->rand($this->table, $this->columns, $this->where);
        if ($result) {
            $this->resultSet = $result;
            if ($limit === 1 || !$limit) {
                $this->resultSet = $this->resultSet[0];
                return $this->asObject();
            }
            $this->resultSet = $result;
        }

        return $result;
    }

    /**
     * Saves and returns the saved item as an object
     * @param array $data The data to save. Must be an associative array
     * @return object The saved object
     *
     * @throws Exception
     * @example ```php
     *    $res = Porm::from('users')->save(['first_name' => 'John', 'last_name' => 'Doe']);
     *    echo $res->id;
     * ```
     *
     */
    public function save(array $data): object
    {
        $this->checkFilterMode("You cannot save at this point in the query, check the usage of the `save()`
         method in the query builder for " . $this->table);

        $this->database->insert($this->table, $data);
        $id = $this->database->id();
        return $this->get($id);
    }

    /**
     * Save multiple items in the database.
     * @param array $data The data to save. Must be an associative array
     * @param bool $returning If true, it returns the resultset containing the saved items
     * @return array|PDOStatement|null
     * @throws Exception
     * @since 2.0.3+ - 2024-08-09
     *@example ```php
     *   $res = table('users')->saveAll([['first_name' => 'John', 'last_name' => 'Doe'], ['first_name' => 'Jane', 'last_name' => 'Doe']]);
     *  $r = table('users')->saveAll([['first_name' => 'John', 'last_name' => 'Doe'], ['first_name' => 'Jane', 'last_name' => 'Doe']], true);
     */
    public function saveAll(array $data, ?bool $returning = true): null|array|PDOStatement
    {
        $this->checkFilterMode("You cannot save at this point in the query, check the usage of the `save()`
         method in the query builder for " . $this->table);
        if (!$returning) {
            $results = null;
            $this->inTransaction(function () use ($data, &$results) {
                $results = $this->database->insert($this->table, $data);
            });
            return $results;
        }
        $results = [];
        $that = $this;
        $this->inTransaction(function () use ($data, $that, &$results) {
            foreach ($data as $datum) {
                $results[] = $that->save($datum);
            }
        });
        return $results;
    }

    /**
     * @param array $data
     * @param array|int|string $where
     * @param string|null $idField
     * @return PDOStatement|null
     * @throws Exception
     */
    public function update(array $data, array|int|string $where, ?string $idField = 'id'): ?PDOStatement
    {
        $this->checkFilterMode("You cannot update at this point in the query, check the usage of the `update()`
         method in the query builder for " . $this->table);
        if (is_int($where) || is_string($where)) {
            $where = [$idField => $where];
        }
        $this->where = array_merge($this->where, $where);
        return $this->database->update($this->table, $data, $this->where);
    }

    /**
     * @throws Exception
     */
    public function asJson(): bool|string
    {
        $this->checkFilterMode("Resultset cannot be jsonified at this point in the query, check the usage of the `asJson()`
         method in the query builder for " . $this->table);
        return $this->resultSet ? json_encode($this->resultSet) : $this->resultSet;
    }

    /**
     * @throws Exception
     */
    public function asObject(): mixed
    {
        $this->checkFilterMode("Resultset cannot be objectified at this point in the query, check the usage of the `asObject()`
         method in the query builder for " . $this->table);
        if (is_array($this->resultSet)) {
            return (object)$this->resultSet;
        }
        return $this->resultSet;
    }


    /**
     * Fetches a single item from the CDatabase.
     *
     * If the where clause is not passed, it fetches the last item in the table.
     * If the where clause is an integer, it fetches the item with the id.
     * If the where clause is an array, it fetches the item that matches the where clause.
     * If the where clause is null, it fetches the last item in the table.
     *
     * @param int|array|string|null $where
     * @param string|null $idField defaults to id, pass this if you want to use a different field as the id other than id
     * @return object|array|null
     * @throws Exception
     * @example ```php
     *    $res1 = Porm::from('users')->get(1); // fetches a user with id 1
     *    $res2 = Porm::from('users')->get(['id' => 1]); // fetches a user with id 1
     *    $res3 = Porm::from('users')->get(['last_name' => 'Pionia', 'first_name'=>'Framework']); // fetches a user with last name Pionia and first_name as Framework
     *   $res4 = Porm::from('users')->get(); // fetches the latest user in the table
     * ```
     */
    public function get(int|array|string|null $where = null, ?string $idField = 'id'): object|array|null
    {
        $this->checkFilterMode("You cannot call `get()` at this point in the query, check the usage of the `get()`
         method in the query builder for " . $this->table);

        if (!$where) {
            $where = ['LIMIT' => 1, 'ORDER' => [$idField => 'DESC']];
        }

        if (is_int($where) || is_string($where)) {
            $where = [$idField => $where];
        }
        $this->where = array_merge($this->where, $where);
        $result = $this->runGet();
        $this->resultSet = $result;
        if ($this->resultSet) {
            return $this->asObject();
        }
        return $result;
    }

    /**
     * Get a resource or throw an exception if it does not exist
     * @version 2.0.3+
     * @since 2.0.3 - 2024-08-09
     * @throws Exception
     */
    public function getOrThrow(int|array|string|null $where = null, string $message = 'Item not found', ?string $idField = 'id'): object|array|null
    {
        $result = $this->get($where, $idField);
        if (!$result) {
            throw new NotFoundException($message);
        }
        return $result;
    }

    /**
     * Create a new item or update an existing item
     * Supports both single and multiple items
     * @throws Exception
     * @since 2.0.3+
     */
    public function saveOrUpdate(array | Arrayable $data, string $pkField = 'id'): object | array
    {
        $this->checkFilterMode("You cannot save at this point in the query, check the usage of the `save()`
         method in the query builder for " . $this->table);
        if (is_array($data)) {
            $data = arr($data);
        }
        if ($data->isEmpty()){
            throw new DatabaseException("You cannot save an empty array");
        }
        // if it is an array of arrays, then we save or update each item
        if (is_array($data->at(0))) {
            $items = [];
            $data->each(function($item) use (&$items, $pkField){
                $items[] = $this->saveOrUpdate(arr($item), $pkField);
            });
            return $items;
        }

        if ($data->has($pkField) && $this->has($id = $data->get($pkField), $pkField)) {

            $this->update($data->toArray(), $id, $pkField);
            return $this->get($id);
        }

        $data->remove($pkField);
        return $this->save($data->all());
    }

    /**
     * Acronym for saveOrUpdate
     * @see $this->saveOrUpdate()
     * @throws Exception
     * @since 2.0.3+
     */
    public function createOrUpdate(array | Arrayable $data, string $pkField = 'id'): object | array
    {
        return $this->saveOrUpdate($data, $pkField);
    }

    /**
     * @param string $query The query to run
     * @param array|null $params The parameters to pass prepare along with the query
     * @return Raw
     * @throws Exception If we are in any other realm than RAW
     */
    public function raw(string $query, ?array $params = []): Raw
    {
        $this->checkFilterMode("You cannot run raw queries at this point in the query, 
        check the usage of the `raw()` method in the query builder for " . $this->table);
        return $this->database::raw($query, $params);
    }

    /**
     * This switches the query to filter mode. It is useful for conditional querying.
     * @param array|null $where The where clause to use
     * @return Builder
     * @throws Exception
     * @example ```php
     *  $res1 = Porm::from('users')->filter(['id' => 1])->get(); // fetches a user with id 1
     *  $res2 = Porm::from('users')->filter(['last_name' => 'Pionia', 'first_name'=>'Framework'])->all(); // fetches all users with last name Pionia and first_name as Framework
     *  $res2 = Porm::from('users')->filter(['last_name' => 'Pionia'])->limit(1)->startAt(2); // fetches a user with last name Pionia and first_name as Framework
     * ```
     */
    public function filter(?array $where = []): Builder
    {
        $this->allowFilterOnly = true;
        if ($where) {
            $this->where = array_merge($this->where, $where);
        }
        return Builder::builder($this->table, $this->database, $this->columns, $this->where)
            ->build();
    }

    /**
     * This defines the table column names to return from the CDatabase
     *
     * If you're in join mode, then all ambigous columns should define the table as an alias
     * @param string|array $columns The columns to select defaults to * for all.
     * @throws Exception
     *
     * @example ```php
     *   $res1 = Porm::from('users')->columns('first_name')->get(1); // fetches the first name of the user with id 1
     *   $res2 = Porm::from('users')->columns(['first_name', 'last_name'])->get(1); // fetches the first name and last name of the user with id 1
     *   $res3 = Porm::from('users')->columns(['first_name', 'last_name'])->filter(['last_name' => 'Pionia'])->all(); // fetches the first name and last name of all users with last name Pionia
     *
     *
     * // In joins
     *
     *  $res4 = Porm::from("users", "u")
     * ->columns(["u.id", "role_name", "role.created_at"])
     * ->join()->left("role", [u.role_id => id])
     * ->all();
     * ```
     */
    public function columns(string|array $columns = "*"): static
    {
        $this->checkFilterMode("You cannot update the columns at this point in the query, 
        the columns method should be called much earlier. Check the usage of the `columns()` method in the query builder for " . $this->table);
        $this->preventRaw = true;
        $this->preventHas = true;
        $this->columns = $columns;
        return $this;
    }

//    /**
//     * This sets the connection to the CDatabase to use for the current query.
//     * It can be used to switch between CDatabase connections.
//     *
//     * @param string|Database|BaseBuilder|ContainerInterface $connection The connection to use, defaults to 'db'
//     * @return TableLevelQueryTrait
//     */
//    public function using(string|Database|BaseBuilder|ContainerInterface $connection = 'db', ?string $containerDbKey = null): static
//    {
//        $this->checkFilterMode('When cannot change the db connection while at this point of the query,
//        check the usage of `using() method in the query builder of `' . $this->table);
//
//        if ($connection instanceof Database) {
//            $this->database = $connection;
//        } else if (is_string($connection)) {
//            $this->database = Database::builder($connection);
//        } else if ($connection instanceof BaseBuilder) {
//            $this->database = $connection->database;
//        } else if ($connection instanceof ContainerInterface && $containerDbKey) {
//            $this->database = $connection->get($containerDbKey);
//        }
//        return $this;
//    }


//    /**
//     * This sets up the CDatabase connection to use internally. It is called when the Porm class is being set up.
//     * @throws Exception
//     */
//    private function reboot(): Core
//    {
//        if ($this->database) {
//            return $this->database;
//        }
//        if ($this->using) {
//            $this->database = CDatabase::builder($this->using);
//        }
//        $this->database = CDatabase::builder();
//        return $this->database;
//    }

    /**
     * This deletes all items that match the where clause
     * @param array $where
     * @return PDOStatement|null
     * @throws Exception
     * @example ```php
     *  $res1 = Porm::from('users')->deleteAll(['name' => 'John']); // deletes all users with name John
     *  $res2 = Porm::from('users')->deleteAll(['last_name' => 'Pionia', 'first_name'=>'Framework']); // deletes all users with last name Pionia and first_name as Framework
     * ```
     */
    public function deleteAll(array $where): ?PDOStatement
    {
        $this->checkFilterMode("You cannot delete at this point in the query, check the usage of the `delete()`
         method in the query builder for " . $this->table);
        return $this->delete($where);
    }

    /**
     * This prevents the use of non-filtering methods in filter mode.
     *
     * Case here is like calling get() on join() yet join() return no resultset yet.
     * @param string $msg The message to throw
     *
     * This is primarily used internally for the purpose.
     * ```php
     * $this->checkFilterMode("You cannot delete at this point in the query, check the usage of the `delete()` method in the query builder for ".$this->table);
     * ```
     * @throws Exception
     */
    private function checkFilterMode($msg = 'Query is in filter mode, you cannot use this method in filter mode'): void
    {
        if ($this->allowFilterOnly) {
            logger()->warning($msg);
            throw new Exception($msg);
        }
    }

    /**
     * This is under the hood similar to deleteOne but it is more explicit
     * @param string|int $id
     * @param string|null $idField
     * @return PDOStatement|null
     * @throws Exception
     */
    public function deleteById(string|int $id, ?string $idField = 'id'): ?PDOStatement
    {
        return $this->delete($id, $idField);
    }

    /**
     * Opens the portal to the joins builder. Once you call this, you can call the join methods
     *
     * @example ```php
     * $res4 = Porm::from("users", "u")
     * ->columns(["u.id", "role_name", "role.created_at"])
     * ->join()->left("role", [u.role_id => id])
     * ->all();
     * ```
     * @param array|null $where
     * @return object|null
     */
    public function join(?array $where = null): ?object
    {
        if ($where) {
            $this->where = array_merge($this->where, $where);
        }
        return Join::builder($this->table, $this->database, $this->columns, $this->where)
            ->build();
    }

    /**
     * This grabs the first [n] items from the CDatabase based on the pkField given
     * @param int|null $size The number of items to fetch
     * @param array|null $where The where clause to use
     * @param string $pkField The primary key field to use
     * @return object|array|null
     * @throws Exception
     */
    public function first(?int $size = 1, ?array $where = [], string $pkField = 'id'): null|object|array
    {
        $this->checkFilterMode("You cannot call `first()` at this point in the query, check the usage of the `first()`
         method in the query builder for " . $this->table);
        $this->where = array_merge($this->where, $where);
        $this->where['LIMIT'] = $size;
        $this->where['ORDER'] = [$pkField => 'DESC'];
        if ($size > 1) {
            return $this->runSelect(null);
        } else {
            $result = $this->runGet();
            $this->resultSet = $result;

            if ($this->resultSet){
                return $this->asObject();
            }

            return $result;
        }
    }

    /**
     * Grab the last item from the CDatabase based on the pkField clause
     * @param int|null $size The number of items to fetch
     * @param array|null $where The where clause to use
     * @param string $pkField The primary key field to use
     * @return object|array|null
     * @throws Exception
     */
    public function last(?int $size = 1, ?array $where = [], string $pkField = 'id'): object|array|null
    {
        $this->checkFilterMode("You cannot call `first()` at this point in the query, check the usage of the `first()`
         method in the query builder for " . $this->table);
        $this->where = array_merge($this->where, $where);
        $this->where['LIMIT'] = $size;
        $this->where['ORDER'] = [$pkField => 'ASC'];
        if ($size > 1) {
            $result = $this->runSelect(null);
        } else {
            $result = $this->runGet();
        }
        $this->resultSet = $result;
        if (count($this->resultSet) === 1) {
            return $this->asObject();
        }
        return $this->resultSet;
    }

}
