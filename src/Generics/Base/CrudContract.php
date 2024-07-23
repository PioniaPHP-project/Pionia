<?php

namespace Pionia\Generics\Base;

use Exception;
use Pionia\Database\PaginationCore;
use Porm\Database\builders\PormObject;
use Porm\exceptions\BaseDatabaseException;
use Porm\Porm;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

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
     * ay the time of querying `name(category_name)` will take precedence of `name` thus we shall end up with
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
        $id = $this->getFieldValue($this->pk_field) ?? throw new Exception("Field {$this->pk_field} is required");
        return $this->getOneInternal($id);
    }

    /**
     * Gets one item from the database. Can be overridden by defining a getOne method in the service
     * @throws Exception
     */
    private function getOneInternal($id): null | array | object
    {
        $customQueried = $this->getItem();
        return $customQueried ?? Porm::from($this->table)
            ->using($this->connection)
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
        $query = Porm::from($this->table)
            ->using($this->connection)
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

    /**
     * Delete in CRUD
     * Handles both post and pre delete events
     * @throws Exception
     */
    protected function deleteItem(): mixed
    {
        $id = $this->getFieldValue($this->pk_field) ?? throw new Exception("Field {$this->pk_field} is required");
        $item = $this->getOneInternal($id);

        if (!$item) {
            throw new Exception("Record with $this->pk_field $id not found");
        }
        $deleted = null;
        // run the before delete event and confirm if its not false or null before proceeding
        if ($this->preDelete($item)) {
            Porm::from($this->table)->inTransaction(function () use ($id, &$deleted) {
                $deleted = Porm::from($this->table)
                    ->using($this->connection)
                    ->delete([$this->pk_field => $id]);
            });
             // run the post delete event
             return $this->postDelete($deleted, $item);
        }
        return null;
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
            throw new Exception("Fields to use for creating undefined in the service");
        }
        foreach ($this->createColumns as $column) {
            if ($this->getFieldValue($column) === null) {
                throw new Exception("Field $column is required");
            }
        }
        $sanitizedData = [];
        foreach ($this->createColumns as $column) {
             $dt = $this->getFieldValue($column);
             if (is_a(FileBag::class, $dt) || is_a(UploadedFile::class, $dt)){
                 $dt = $this->handleUpload($dt, $column);
             }
             if ($dt) {
                 $sanitizedData[$column] = $dt;
             }
        }

        $saved = null;
        if ($toSave = $this->preCreate($sanitizedData)) {
            Porm::from($this->table)->inTransaction(function () use (&$saved, $toSave) {
                $saved = Porm::from($this->table)
                    ->using($this->connection)
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
        $paginator = new PaginationCore($this->request->getData(), $this->table, $this->limit, $this->offset, $this->connection);
        $prep1 =  $paginator->columns($this->getListColumns());

        $prep1->init(function (PormObject $query) {
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
        $data = $this->request->getData();
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

        return Porm::from($this->table)
            ->using($this->connection)
            ->random($limit);
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
        $id = $this->getFieldValue($this->pk_field) ?? throw new Exception("Field {$this->pk_field} is required");

        // get the item to be updated

        $item = Porm::from($this->table)
            ->using($this->connection)
            ->get($id, $this->pk_field);

        if (!$item) {
            throw new Exception("Record with id {$id} not found");
        }

        if (is_array($item)) {
            $toArray = $item;
        } else {
            $toArray = (array)$item;
        }
        // if the developer defines the columns to update, we stick to those
        if ($this->updateColumns) {
            foreach ($this->updateColumns as $column) {
                if ($this->getFieldValue($column) !== null) {
                    $dt = $this->getFieldValue($column);

                    if (is_a(FileBag::class, $dt) || is_a(UploadedFile::class, $dt)){
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

                    if (is_a(FileBag::class, $dt) || is_a(UploadedFile::class, $dt)){
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
            Porm::from($this->table)->inTransaction(function () use ($toSave, $id, &$updated) {
                Porm::from($this->table)
                    ->using($this->connection)
                    ->update($toSave, [$this->pk_field => $id]);
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
