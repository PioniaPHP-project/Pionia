<?php

namespace Pionia\Generics\Base;

use Exception;
use Pionia\Database\PaginationCore;
use Porm\exceptions\BaseDatabaseException;
use Porm\Porm;

trait CrudContract
{
    private function detectAndAddColumns(): void
    {
        $data = $this->request->getData();
        if (isset($data['columns']) || isset($data['COLUMNS'])) {
            $this->listColumns = $data['columns'] ?? $data['COLUMNS'];
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
        $data = $this->request->getData();
        $id = $data[$this->pk_field] ?? throw new Exception("Field {$this->pk_field} is required");
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
        $data = $this->request->getData();
        $id = $data[$this->pk_field] ?? throw new Exception("Field {$this->pk_field} is required");
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
        $data = $this->request->getData();
        if (!$this->createColumns) {
            throw new Exception("Fields to use for creating were not defined in the service");
        }
        foreach ($this->createColumns as $column) {
            if (!isset($data[$column])) {
                throw new Exception("Field $column is required");
            }
        }
        $sanitizedData = [];
        foreach ($this->createColumns as $column) {
            $sanitizedData[$column] = trim($data[$column]);
        }

        $saved = null;
        if ($toSave = $this->preCreate($sanitizedData)) {
            Porm::from($this->table)->inTransaction(function () use ($data, &$saved, $toSave) {
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
     */
    protected function paginate(): ?array
    {
        $paginator = new PaginationCore($this->request->getData(), $this->table, $this->limit, $this->offset, $this->connection);
        return $paginator->columns($this->getListColumns())
            ->paginate();
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
        $data = $this->request->getData();
        $limit = $data['limit'] ?? $data['size']?? 1;

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
        $data = $this->request->getData();
        $id = $data[$this->pk_field] ?? throw new Exception("Field {$this->pk_field} is required");

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
                if (isset($data[$column])) {
                    $toArray[$column] = $data[$column];
                }
            }
        } else {
            foreach ($toArray as $key => $value) {
                if (isset($data[$key])) {
                    $toArray[$key] = $data[$key];
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
