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

    private array $reqData = [];

    private array $parged = ['columns' => '*', 'where' => []];

    private string $table;

    public function __construct(?array $reqData, string $table,  ?int $limit = 10, ?int $offset = 0, ?string $db = 'db')
    {
        $this->limit = $limit;
        $this->offset = $offset;
        $this->table = $table;
        $this->reqData = $reqData;
        $this->db = $db;
        $this->parged = array_merge($this->parged, $this->extractPagination());
    }


    public function where(array $where = []): PaginationCore
    {
        $this->parged['where'] = array_merge($this->parged['where'], $where);
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
     * Should be called finally to get the paginated data.
     *
     * Supports where clause just like the `where` method in the Porm query builder
     * @throws BaseDatabaseException
     * @throws Exception
     */
    public function paginate(?array $where = null): ?array
    {
        if ($where) {
            $this->parged['where'] = array_merge($this->parged['where'], $where);
        }

        $limit = $this->parged['LIMIT'];
        $offset = $this->parged['OFFSET'];

        $baseQuery = Porm::from($this->table)
            ->columns($this->parged['columns'])
            ->using($this->db)
            ->filter($this->parged['where']);
        // before applying the offsets, get the total count
        $all = $baseQuery->count();
        // apply the limit and offset
        $resultSet = $baseQuery
            ->limit($limit)
            ->startAt($offset)
            ->all();
        $prev = $offset - $limit;
        $next = $offset + $limit;

        // check if there are more results
        $nextOffset = $next < $all ? $next : null;

        $prevOffset = max($prev, 0);

        // has next page
        $hasNext = $nextOffset !== null;
        $hasPrevious = $prevOffset && $prevOffset > 0;

        return [
            'results' => $resultSet,
            'current_limit' => $limit,
            'current_offset' => $offset,
            'next_offset' => $nextOffset,
            'prev_offset' => $prevOffset,
            'results_count' => count($resultSet),
            'has_next' => $hasNext,
            'has_previous' => $hasPrevious,
            'total_count' => $all
        ];
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
        $data = $this->reqData;
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
