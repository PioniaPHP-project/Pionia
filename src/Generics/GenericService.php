<?php

namespace Pionia\Generics;

use Exception;
use PDOStatement;
use Pionia\Request\BaseRestService;
use Pionia\Request\PaginationCore;
use Porm\exceptions\BaseDatabaseException;
use Porm\Porm;

class GenericService extends BaseRestService
{
    public string $table;

    public int $limit = 10;

    public int $offset = 0;

    public string $pk_field = 'id';

    public string $connection = 'db';

    public array | string $listColumns = '*';

    public ?array $createColumns = null;

    public ?array $updateColumns = null;

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
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function getOne(): ?array
    {
        $data = $this->request->getData();
        $id = $data[$this->pk_field] ?? throw new Exception("Field {$this->pk_field} is required");
        return Porm::from($this->table)
            ->using($this->connection)
            ->columns($this->listColumns)
            ->get($id, $this->pk_field);
    }

    /**
     * Retrieve all in CRUD
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function allItems(): ?array
    {
        return Porm::from($this->table)
            ->using($this->connection)
            ->columns($this->listColumns)
            ->all();
    }

    /**
     * Delete in CRUD
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function deleteItem(): PDOStatement
    {
        $data = $this->request->getData();
        $id = $data[$this->pk_field] ?? throw new Exception("Field {$this->pk_field} is required");
        return Porm::from($this->table)
            ->using($this->connection)
            ->delete($id, $this->pk_field);
    }

    /**
     * Create in CRUD
     * Saves in transactions
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function createItem(): ?object
    {
        $data = $this->request->getData();
        foreach ($this->createColumns as $column) {
            if (!isset($data[$column])) {
                throw new Exception("Column {$column} is required");
            }
        }
        $sanitizedData = [];
        foreach ($this->createColumns as $column) {
            $sanitizedData[$column] = trim($data[$column]);
        }
        try {
            Porm::from($this->table)->pdo()->beginTransaction();
            $saved =  Porm::from($this->table)
                ->using($this->connection)
                ->save($sanitizedData);
            Porm::from($this->table)->pdo()->commit();
            return $saved;
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
            ->columns($this->listColumns)
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
            // update the item
            foreach ($data as $key => $value) {
                $item->$key = $value;
            }

            Porm::from($this->table)
                ->using($this->connection)
                ->update($item, $id, $this->pk_field);

            Porm::from($this->table)->pdo()->commit();

            return Porm::from($this->table)
                ->using($this->connection)
                ->columns($this->listColumns)
                ->get($id, $this->pk_field);
        } catch (Exception $e) {
            Porm::from($this->table)->pdo()->rollBack();
            throw new Exception($e->getMessage());
        }
    }

}
