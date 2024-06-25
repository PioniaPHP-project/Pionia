<?php

namespace Pionia\Request;

use Exception;
use Porm\exceptions\BaseDatabaseException;
use Porm\Porm;

class PaginationCore
{
    public int $limit = 10;

    public int $offset = 0;

    private string $db = 'db';

    private Request $request;

    private array $parged = ['columns' => '*'];

    private string $table;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Paginates the data
     */
    public function builder(string $table, Request $request, ?int $limit = 10, ?int $offset = 0, ?string $db = 'db'): PaginationCore
    {
        $paginator = new PaginationCore($request);
        $paginator->limit = $limit;
        $paginator->offset = $offset;
        $paginator->table = $table;
        $paginator->db = $db;
        $this->parged = array_merge($this->parged, $paginator->extractPagination());
        return $this;
    }

    /**
     * Sets the columns to return in the queryset
     * @param string|array $columns
     * @return $this
     */
    public function columns(string | array $columns = "*"): PaginationCore
    {
        $this->parged['columns'] = $columns;
        return $this;
    }

    /**
     * Should be called finally to get the paginated data
     * @throws BaseDatabaseException
     * @throws Exception
     */
    public function paginate(): ?array
    {
        $limit = $this->parged['LIMIT'];
        $offset = $this->parged['OFFSET'];

        return Porm::from($this->table)
            ->columns($this->parged['columns'])
            ->using($this->db)
            ->filter()
            ->limit($limit)
            ->startAt($offset)
            ->all();
    }

    /**
     * Picks the pagination data from the request. Pagination data can be defined in the `PAGINATION` key or the `pagination` key.
     * You can also define them in the `SEARCH` key or the `search` key.
     *
     * Pagination usually is comprised of the `limit` and `offset` or `LIMIT` and `OFFSET` keys.
     *
     * If none can't be found, it implies the request is new, so the default values are used.
     * @return array
     */
    private function extractPagination(): array
    {
        $data = $this->request->getData();
        $search = null;

        if (isset($data['PAGINATION']) || isset($data['pagination']) || isset($data['SEARCH']) || isset($data['search'])) {
            $search = $data['PAGINATION'] ?? $data['pagination'] ?? $data['SEARCH'] ?? $data['search'] ?? null;
        }

        if ($search) {
            $this->limit = $search['limit'] ?? $this->limit;
            $this->offset = $search['offset'] ?? $this->offset;
        } else {
            if (isset($data['limit']) || isset($data['LIMIT'])){
                $this->limit = $data['limit'] ?? $data['LIMIT'] ?? $this->limit;
            }

            if (isset($data['offset']) || isset($data['OFFSET'])){
                $this->offset = $data['offset'] ?? $data['OFFSET'] ?? $this->offset;
            }
        }

        return [
            'OFFSET' => $this->offset,
            'LIMIT' => $this->limit,
        ];
    }


}
