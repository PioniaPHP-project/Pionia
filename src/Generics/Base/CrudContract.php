<?php

namespace Pionia\Generics\Base;

use Exception;
use PDOStatement;
use Pionia\Request\PaginationCore;
use Porm\exceptions\BaseDatabaseException;
use Porm\Porm;

trait CrudContract
{
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
    protected function getOne(): ?array
    {
        $once = $this->getOne();
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
    private function getOneInternal($id): ?array
    {
        $customQueried = $this->getOne();
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
            throw new Exception("Item with $this->pk_field $id not found");
        }
        // run the before delete event and confirm if its not false or null before proceeding
        if ($this->preDelete($item)) {
             $deleted = Porm::from($this->table)
                ->using($this->connection)
                ->delete([$this->pk_field => $id]);

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
        $data = $this->request->getData();
        foreach ($this->createColumns as $column) {
            if (!isset($data[$column])) {
                throw new Exception("Column $column is required");
            }
        }
        $sanitizedData = [];
        foreach ($this->createColumns as $column) {
            $sanitizedData[$column] = trim($data[$column]);
        }
        try {
            Porm::from($this->table)->pdo()->beginTransaction();
            // run the pre create event and confirm if its not false or null before proceeding
            if ($toSave = $this->preCreate($sanitizedData)) {

                $saved = Porm::from($this->table)
                    ->using($this->connection)
                    ->save($toSave);
                Porm::from($this->table)->pdo()->commit();
                return $this->postCreate($saved);
            }
            Porm::from($this->table)->pdo()->rollBack();
            return null;
        } catch (Exception $e) {
            Porm::from($this->table)->pdo()->rollBack();
            throw new Exception($e->getMessage());
        }
    }


    /**
     * @throws BaseDatabaseException
     */
    protected function paginate(): ?array
    {
        $paginator = new PaginationCore($this->request);
        return $paginator->builder($this->table, $this->request, $this->limit, $this->offset, $this->connection)
            ->columns($this->getListColumns())
            ->paginate();
    }

    /**
     * Retrieve all with pagination
     * @throws Exception
     */
    protected function getAllWithPagination(): ?array
    {
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
        $data = $this->request->getData();
        $id = $data[$this->pk_field] ?? throw new Exception("Field {$this->pk_field} is required");

        // get the item to be updated

        $item = Porm::from($this->table)
            ->using($this->connection)
            ->get($id, $this->pk_field);

        if (!$item) {
            throw new Exception("Item with id {$id} not found");
        }

        try {
            Porm::from($this->table)->pdo()->beginTransaction();

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
            // run the pre update event and confirm if its not false or null before proceeding
            if ($toSave = $this->preUpdate($toArray)) {

                Porm::from($this->table)
                    ->using($this->connection)
                    ->update($toSave, [$this->pk_field => $id]);
            }

            Porm::from($this->table)->pdo()->commit();

            $updated = $this->getOneInternal($id);
            // run the post update event
            return $this->postUpdate($updated);
        } catch (Exception $e) {
            Porm::from($this->table)->pdo()->rollBack();
            throw new Exception($e->getMessage());
        }
    }
}
