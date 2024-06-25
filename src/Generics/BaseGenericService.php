<?php

namespace Pionia\Generics;

use Exception;
use PDOStatement;
use Pionia\Request\BaseRestService;
use Pionia\Request\PaginationCore;
use Porm\exceptions\BaseDatabaseException;
use Porm\Porm;

class BaseGenericService extends BaseRestService
{
    public string $table;

    public int $limit = 10;

    public int $offset = 0;

    public string $pk_field = 'id';

    public string $connection = 'db';

    public array | string $columns = '*';

    /**
     * @throws BaseDatabaseException
     */
    protected function paginate(): ?array
    {
        $paginator = new PaginationCore($this->request);
        return $paginator->builder($this->table, $this->request, $this->limit, $this->offset, $this->connection)
            ->columns($this->columns)
            ->paginate();
    }

    /**
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function getOne($id): ?array
    {
        return Porm::from($this->table)
            ->using($this->connection)
            ->get($id, $this->pk_field);
    }

    /**
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function all(): ?array
    {
        return Porm::from($this->table)
            ->using($this->connection)
            ->columns($this->columns)
            ->all();
    }

    /**
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function delete($id): ?PDOStatement
    {
        return Porm::from($this->table)
            ->using($this->connection)
            ->delete($id, $this->pk_field);
    }

    /**
     * @throws BaseDatabaseException
     * @throws Exception
     */
    protected function create(array $data): ?object
    {
        return Porm::from($this->table)
            ->using($this->connection)
            ->save($data);
    }

}
