<?php

namespace Pionia\Http\Services\Generics\Contracts;

use Exception;
use Pionia\Porm\Core\Porm;
use Pionia\Porm\Exceptions\BaseDatabaseException;
use Pionia\Porm\PaginationCore;
use Symfony\Component\HttpFoundation\File\UploadedFile;

trait CrudContract
{
    /**
     * Checks if the frontend has defined columns we should query by.
     * Works for both joined and non-joined querying.
     * It pays respect to aliases defined, so, don't forget to respect them too!
     * @return void
     */
    private function detectAndAddColumns(): void
    {
        if ($this->getFieldValue("dontRelate")){
            $this->dontRelate = $this->getFieldValue("dontRelate");
        }

        if ($this->getFieldValue("columns") || $this->getFieldValue("COLUMNS")) {
            $this->listColumns = $this->getFieldValue("columns") ?? $this->getFieldValue("COLUMNS");
        }

        if ($this->dontRelate){
            $this->cleanRelationColumns();
        }
    }

    /**
     * If the fields are already in the format of relationships, this method reverses that
     * including removing duplicates.
     *
     * @example ```
     * $this->listColumns = ["alias2.name", "alias1.name(category_name), "alias1.id"];
     * // this will become
     * $this->listColumns = ["name", "name(category_name), "id"];
     * ```
     * by the time of querying `name(category_name)` will take precedence of `name` thus we shall end up with
     * `category_name` and `id` in the response
     * @return void
     */
    private function cleanRelationColumns(): void
    {
        if ($this->getListColumns() !== "*"){
            $columns = [];
            foreach ($this->getListColumns() as $column){
                if (str_contains($column, ".")){
                    $results = explode(".", $column);
                    $column = $results[1];

                    if (str_contains($column, "(")){
                        $checker = explode("(", $column);
                        $final = $checker[0];
                        array_map(function ($item) use($final, &$column) {
                            if (str_starts_with($item, $final)){
                                $column= null;
                            }
                            return [];
                        }, $columns);
                    }
                }
                if ($column) {
                    $columns[] = $column;
                }
            }
            $this->listColumns = $columns;
        }
    }

    /**
     * Returns the columns we shall query from the db while querying
     * @return array|string
     */
    private function getListColumns(): array|string
    {
        return $this->listColumns ?? '*';
    }

    /**
     * Detects if we have pagination params anywhere in the request.
     * For pagination to kick-in, both offset and limit must be defined at any of the levels
     * defined by `$this->detectPagination()`
     * @see $this->detectPagination()
     * @param array $data
     * @return bool
     */
    protected function _checkPaginationInternal(array $data): bool
    {
        $limitSet = false;
        $offsetSet = false;
        if (isset($data['limit']) && is_numeric($data['limit']) || isset($data['LIMIT']) && is_numeric($data['LIMIT'])){
            $limitSet = true;
        }

        if (isset($data['offset']) && is_numeric($data['offset']) || isset($data['OFFSET']) && is_numeric($data['OFFSET'])){
            $offsetSet = true;
        }

        return $limitSet && $offsetSet;
    }
    /**
     * Detect if our pagination params are defined anywhere in the request.
    *
     * Remember these can in defined in one of the following:-
     *
     * On the request root level
    * @example ```json
     * {
     *     SERVICE: "ourService",
     *     ACTION: "ourAction",
     *     LIMIT: 10, // can also be `limit: 10`
     *     OFFSET: 5 // can also be `offset: 5`
     * }
     *
     * Or they can be defined under the PAGINATION/pagination key
     *
     * @example ```json
     * {
     *     SERVICE: "ourService",
     *     ACTION: "ourAction",
     *     PAGINATION:{ // can also be `pagination`
     *          LIMIT: 10, // can also be `limit: 10`
     *          OFFSET: 5 // can also be `offset: 5`
     *      }
     * }
     *
     * Or in the SEARCH/search param
     *
     * @example
     * {
     *     SERVICE: "ourService",
     *     ACTION: "ourAction",
     *     SEARCH:{ // can also be `search`
     *        LIMIT: 10, // can also be `limit: 10`
     *        OFFSET: 5 // can also be `offset: 5`
     *     }
     *  }
     * }
     * */
    protected function detectPagination(array $reqData): bool
    {
        if ($reqData) {
            $data = $reqData;
            if ($this->_checkPaginationInternal($data)) {
                return true;
            }

            if (isset($data['pagination']) || isset($data['PAGINATION'])) {
                $data = $data['pagination'] ?? $data['PAGINATION'];
                if ($this->_checkPaginationInternal($data)) {
                    return true;
                }
            }

            if (isset($data['search']) || isset($data['SEARCH'])) {
                $data = $data['search'] ?? $data['SEARCH'];
                if ($this->_checkPaginationInternal($data)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Retrieve in CRUD, returns on Item at a time.
     * @throws Exception
     */
    protected function getOne(): ?object
    {
        $this->detectAndAddColumns();
        $once = $this->getItem();
        if ($once) {
            return $once;
        }
        if ($this->weShouldJoin()) {
            return $this->getOneJoined();
        }
        $id = $this->getFieldValue($this->primaryKey()) ?? throw new Exception("Field {$this->primaryKey()} is required");
        return $this->getOneInternal($id);
    }

    /**
     * Gets one item from the database. Can be overridden by defining a getOne method in the service
     * @throws Exception
     */
    private function getOneInternal($id): null | array | object
    {
        $customQueried = $this->getItem();
        return $customQueried ??  table($this->table, null, $this->connection)
            ->columns($this->getListColumns())
            ->get([$this->pk_field => $id]);
    }

    protected function hasLimit()
    {
        return $this->getFieldValue("LIMIT") ?? $this->getFieldValue("limit") ?? false;
    }

    protected function hasOffset()
    {
        return $this->getFieldValue("OFFSET") ?? $this->getFieldValue("offset") ?? false;
    }
    /**
     * Retrieve all in CRUD
     *
     * Can be overridden by defining a getItems method in the service
     * @return array|null
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function allItems(): ?array
    {
        if($this->getItems()){
            return $this->getItems();
        }

        if ($this->weShouldJoin()) {
            return $this->getAllItemsJoined();
        }
        $query =  table($this->table, null, $this->connection)
            ->columns($this->getListColumns())
            ->filter();

            if ($this->hasLimit()){
                $query->limit($this->hasLimit());
            }

            if ($this->hasOffset()){
                $query->startAt($this->hasOffset());
            }
        return $query->all();
    }

    protected function isInJoinMode(): bool
    {
        if ($this->joins !== null && count($this->joins) > 0){
            return true;
        }
        return false;
    }

    /**
     * Delete in CRUD
     * Handles both post and pre delete events
     * Deletes in joined mode if defined too, but will delete only from the main table
     * @throws Exception
     */
    protected function deleteItem(): mixed
    {
        $pk = $this->pk_field;
        if ($this->isInJoinMode()){
            $pk = str_contains($this->pk_field, ".") ? explode(".", $this->pk_field)[1] : $this->pk_field;
        }

        $id = $this->getFieldValue($this->pk_field) ?? $this->getFieldValue($pk) ?? throw new Exception("Field {$this->pk_field} is required");
        $item = $this->getOneInternal($id);

        if (!$item) {
            throw new Exception("Record with $this->pk_field $id not found");
        }
        $deleted = null;
        // run the before delete event and confirm if its not false or null before proceeding
        if ($this->preDelete($item)) {
            table($this->table, $this->connection)->inTransaction(function () use ($id, &$deleted) {
                $deleted =  table($this->table, $this->connection)
                    ->delete([$this->pk_field => $id]);
            });
             // run the post delete event
             return $this->postDelete($deleted, $item);
        }
        return null;
    }

    /**
     * @throws Exception
     */
    protected function checkIfFieldPassesAllValidations($field)
    {
        $column = $field;
        $required = true;

        if (is_array($field)){
            $column = key($field);
            $required = isset($field['required']) && $field['required'];
        }

        $dt = $this->getFieldValue($column);
        if ($required && $dt  === null) {
            throw new Exception("Field $column is required");
        }

        if ($dt && is_a(UploadedFile::class, $dt)){
            $dt = $this->handleUpload($dt, $column);
        }

        return $dt;
    }
    /**
     * Create in CRUD
     * Saves in transactions, runs pre and post create events
     * @throws Exception
     */
    protected function createItem(): ?object
    {
        $this->detectAndAddColumns();
        if (!$this->createColumns) {
            throw new Exception("Create columns undefined!");
        }

        $sanitizedData = [];
        foreach ($this->createColumns as $column) {
            $required = true;
            if(str_ends_with($column, "?")) {
                $column = trim(str_replace("?", "", $column));
                $required = false;
            }
            $dt = $this->getFieldValue($column);
            if ($dt instanceof UploadedFile){
                $dt = $this->handleUpload($dt, $column);
            }
            if ($required && $dt === null) {
                throw new Exception("Field $column is required");
            }
            if ($dt !== null) {
                $sanitizedData[$column] = $dt;
            }
        }

        $saved = null;
        if ($toSave = $this->preCreate($sanitizedData)) {
            table( $this->table, $this->connection)->inTransaction(function () use (&$saved, $toSave) {
                $saved =  table($this->table, $this->connection)
                    ->save($toSave);
            });
        }
        if (!$saved) {
            throw new Exception("Record not saved! Try again later.");
        }
        return $this->postCreate($saved);
    }


    /**
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function paginate(): ?array
    {
        $paginator = new PaginationCore(
            reqData: $this->request->getData()->all(),
            table: $this->table,
            limit: $this->limit,
            offset: $this->offset,
            db: $this->connection);
        $prep1 =  $paginator->columns($this->getListColumns());

        $prep1->init(function (Porm $query) {
            if ($this->weShouldJoin()) {
                 $join = $query->join();
                return $this->attachJoins($join);
            }
            return $query->filter();
        });
        return $prep1->paginate();
    }

    /**
     * Retrieve all with pagination
     * @throws Exception
     */
    protected function getAllWithPagination(): ?array
    {
        $this->detectAndAddColumns();
        $data = $this->request->getData()->all();
        if ($this->detectPagination($data)) {
            return $this->paginate();
        }
        return $this->allItems();
    }

    /**
     * @throws Exception
     */
    protected function randomItem()
    {
        $this->detectAndAddColumns();
        $limit = $this->getFieldValue('limit') ?? $this->getFieldValue('size') ?? 1;

        return table($this->table, $this->connection)
            ->random($limit);
    }

    /**
     * Returns the primary key field
     * @throws Exception
     */
    private function primaryKey(): bool|string
    {
        if ($this->isInJoinMode() && $this->pk_field){
            return str_contains($this->pk_field, ".") ? explode(".", $this->pk_field)[1] : $this->pk_field;
        }
        return $this->pk_field;
    }

    /**
     * Updated an item in the db.
     *
     * If updateColumns are defined, it only updates those.
     *
     * It also calls both preUpdate and postUpdate hooks if defined
     * @throws Exception
     */
    protected function updateItem(): object|array|null
    {
        $this->detectAndAddColumns();
        $id = $this->getFieldValue($this->primaryKey()) ?? throw new Exception("Field {$this->primaryKey()} is required");

        // get the item to be updated

        $item = table($this->table, $this->connection)
            ->get($id, $this->primaryKey());

        if (!$item) {
            throw new Exception("Record with id {$id} not found");
        }

        $toArray = is_array($item) ? $item : (array) $item;

        // if the developer defines the columns to update, we stick to those
        if ($this->updateColumns) {
            foreach ($this->updateColumns as $column) {
                if ($this->getFieldValue($column) !== null) {
                    $dt = $this->getFieldValue($column);

                    if (is_a(UploadedFile::class, $dt)){
                        $dt = $this->handleUpload($dt, $column);
                    }
                    if ($dt) {
                        $toArray[$column] = $dt;
                    }
                }
            }
        } else {
            foreach ($toArray as $key => $value) {
                if ($this->getFieldValue($key) !== null) {
                    $dt = $this->getFieldValue($key);

                    if (is_a(UploadedFile::class, $dt)){
                        $dt = $this->handleUpload($dt, $key);
                    }
                    if ($dt) {
                        $toArray[$key] = $dt;
                    }
                }
            }
        }
        $updated = null;
        // run the pre update event and confirm if its not false or null before proceeding
        if ($toSave = $this->preUpdate($toArray)) {
            table($this->table, $this->connection)
                ->inTransaction(function () use ($toSave, $id, &$updated) {
                    table($this->table, $this->connection)->update($toSave, [$this->primaryKey() => $id]);
            });
            $updated = $this->getOneInternal($id);
        }
        if (!$updated) {
            throw new Exception("Update failed for record with id $id");
        }
        // run the post update event
        return $this->postUpdate($updated);
    }
}
