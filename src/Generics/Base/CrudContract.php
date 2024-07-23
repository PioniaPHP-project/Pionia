<?php

namespace Pionia\Generics\Base;

use Exception;
use Pionia\Database\PaginationCore;
use Porm\Database\builders\PormObject;
use Porm\exceptions\BaseDatabaseException;
use Porm\Porm;

trait CrudContract
{
    private function detectAndAddColumns(): void
    {
        if ($this->getFieldValue("columns") || $this->getFieldValue("COLUMNS")) {
            $this->listColumns = $this->getFieldValue("columns") ?? $this->getFieldValue("COLUMNS");
        }
    }

    private function getListColumns(): array|string
    {
        return $this->listColumns ?? '*';
    }

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
     * Retrieve in CRUD
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
        $customMultipleQueried = $this->getItems();

        if ($this->weShouldJoin() && !$customMultipleQueried) {
            return $this->getAllItemsJoined();
        }
        return $customMultipleQueried ?? Porm::from($this->table)
            ->using($this->connection)
            ->columns($this->getListColumns())
            ->all();
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
            if (!$this->getFieldValue($column)) {
                throw new Exception("Field $column is required");
            }
        }
        $sanitizedData = [];
        foreach ($this->createColumns as $column) {
            $sanitizedData[$column] = trim($this->getFieldValue($column));
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
                if ($this->getFieldValue($column)) {
                    $toArray[$column] = $this->getFieldValue($column);
                }
            }
        } else {
            foreach ($toArray as $key => $value) {
                if ($this->getFieldValue($key)) {
                    $toArray[$key] = $this->getFieldValue($key);
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
