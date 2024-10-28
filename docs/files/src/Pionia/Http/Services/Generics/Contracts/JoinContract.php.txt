<?php

namespace Pionia\Http\Services\Generics\Contracts;

use Exception;
use Pionia\Http\Services\JoinType;
use Pionia\Porm\Database\Builders\Join;

trait JoinContract
{
    /**
     * @var ?array An associative array of tables to be used for joins with the table in the service.
     * The key is the table name and the value is an array of the columns to join on.
     *
     * @example
     * ```php
     * [
     *    'student' => ['id' => 'student_id'],
     * ]
     * ```
     * This will join the `student` table on the `id` column of the current table and the `student_id` column of the `student` table.
     */
    public ?array $joins = null;

    /**
     * @var ?array An associative array of join types to be used for joins with the table in the service.
     * The key is the table name and the value is the join type.
     *
     * @example
     * ```php
     * [
     *    'student' => JoinType::INNER,
     * ]
     * ```
     * This will join the `student` table using an inner join.
     */
    public ?array $joinTypes = null;

    /**
     * @var ?array An associative array of aliases to be used for joins with the table in the service.
     * The key is the table name and the value is the alias.
     *
     * @example
     * ```php
     * [
     *    'student' => 's',
     * ]
     * ```
     * This will join the `student` table using the alias `s`.
     */
    public ?array $joinAliases = null;

//    /**
//     * @var ?array An associative array of columns to be returned from the joined tables.
//     * The key is the table name and the value is an array of columns to return.
//     *
//     * @example
//     * ```php
//     * [
//     *    'student' => ['name', 'age'],
//     * ]
//     * ```
//     * This will return the `name` and `age` columns from the `student` table.
//     *
//     * If you want to return all columns from the joined table, you can use the `*` wildcard.
//     */
//    public ?array $joinColumns = null;

    /**
     * @var JoinType The default join type to use when joining tables.
     */
    public JoinType $defaultJoinType = JoinType::INNER;

    /**
     * Detects whether to enter to enter the join mode or stay in the normal mode.
     * Normal mode queries one table at a time.
     */
    private function weShouldJoin(): bool
    {
        return !empty($this->joins) && !$this->dontRelate;
    }

//    /**
//     * Retrieves the colum sets that will help us to create the `on` part of the query
//     * @throws Exception
//     */
//    private function resolveJoinColumns(): array|string
//    {
//        if (empty($this->joinColumns)){
//            return $this->getListColumns();
//        }
//
//        $columns = [];
//        foreach ($this->joinColumns as $table => $cols){
//            $alias = $this->resolveAliases()[$table] ?? $table;
//            $dottedCols = array_map(fn($col) => !str_contains($col, ".") && "$alias.$col", $cols);
//
//            $columns = array_merge($columns, $dottedCols);
//        }
//        return $columns;
//    }


//    /**
//     * Matches every table in the `joins` property with its alias.
//     * @return array
//     * @throws Exception
//     */
//    private function resolveAliases(): array
//    {
//        $tables = array_keys($this->joins);
//        $aliases = [];
//
//        foreach ($tables as $table){
//            $aliases[$table] = $this->joinAliases[$table] ?? $table;
//        }
//        return $aliases;
//    }


    /**
     * Will be used when we are getting a single item.
     * @throws Exception
     */
    private function getOneJoined(): ?object
    {
        $data = $this->request->getData();
        $id = $data[$this->pk_field] ?? throw new Exception("Field {$this->pk_field} is required");
        $items = $this->attachJoins()->where([$this->pk_field => $id])->limit(1)->all();

        if (count($items) > 0){
            return json_decode(json_encode($items[0]));
        }
        return null;
    }

    /**
     * Will be used when we are listing everything.
     * @throws Exception
     */
    private function getAllItemsJoined(): array
    {
        $query = $this->attachJoins();
        if ($this->hasLimit()){
            $query->limit($this->hasLimit());
        }
        if ($this->hasOffset()){
            $query->startAt($this->hasOffset());
        }
        return $query->all();
    }

    /**
     * Attaches all defined joins to the base query.
     * @throws Exception
     */
    private function attachJoins(?Join $join = null): Join
    {
        $query = $join ?? $this->joinQuery();

        if (!$this->joinTypes){
            foreach ($this->joins as $table => $columns){
                $query = $this->switchJoin($this->defaultJoinType, $query, $table);
            }
            return $query;
        }

        foreach ($this->joinTypes as $table => $joinType){
            $query = $this->switchJoin($joinType, $query, $table);
        }
        return $query;
    }

    /**
     * @param JoinType $joinType The type of join to use
     * @param Join $query The current query we are joining to
     * @param string $table The table to join
     * @return Join The query with the join added
     */
    private function switchJoin(JoinType $joinType, Join $query, string $table): Join
    {
        switch ($joinType){
            case JoinType::INNER:
                $query->inner($table, $this->joins[$table], $this->joinAliases[$table] ?? $table);
                break;
            case JoinType::LEFT:
                $query->left($table, $this->joins[$table], $this->joinAliases[$table] ?? $table);
                break;
            case JoinType::RIGHT:
                $query->right($table, $this->joins[$table], $this->joinAliases[$table] ?? $table);
                break;
            case JoinType::FULL:
                $query->full($table, $this->joins[$table], $this->joinAliases[$table] ?? $table);
                break;
        }
        return $query;
    }

    /**
     * Resolve the base query to base on to start joining.
     * If the developer has not yet defined any joins, we drop it and generate a new one.
     * @return Join
     * @throws Exception
     */
    private function joinQuery(): Join
    {
        $this->detectAndAddColumns();
        $theJoin = $this->getJoinQuery();
        if (!$theJoin || empty($theJoin->getJoins())){
           $theJoin = table($this->table, $this->connection)
                ->columns($this->getListColumns())
                ->join();
        }
        return $theJoin;
    }


}
