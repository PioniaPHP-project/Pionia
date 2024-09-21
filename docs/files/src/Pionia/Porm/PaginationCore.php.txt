<?php

namespace Pionia\Porm;

use Exception;
use Pionia\Porm\Database\Builders\Builder;
use Pionia\Porm\Database\Builders\Join;
use Pionia\Porm\Exceptions\BaseDatabaseException;

class PaginationCore
{
    public int $limit = 10;

    public int $offset = 0;

    private ?string $db = null;

    private array $reqData = [];

    private array $parged = ['columns' => '*', 'where' => []];

    private string $table;

    private Join | Builder | null $baseQuery = null;

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
     * Sets the columns to return in the query
     * @param string|array $columns
     * @return $this
     */
    public function columns(string | array $columns = "*"): PaginationCore
    {
        $this->parged['columns'] = $columns;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function init(callable $callback): PaginationCore
    {
        $query =  table($this->table, $this->db)
            ->columns($this->parged['columns'])
            ->where($this->parged['where']);

        $this->baseQuery = $callback($query);

        if (!is_a($this->baseQuery, Join::class) && !is_a($this->baseQuery, Builder::class)){
            throw new Exception("Invalid query builder returned from the callback");
        }
        return $this;
    }

    /**
     * Should be called finally to get the paginated data.
     *
     * Supports where clause just like the `where` method in the Porm query builder
     * @throws BaseDatabaseException
     * @throws Exception
     */
    public function paginate(?array $where = null, ?array $joins = null): ?array
    {
        if ($where) {
            $this->parged['where'] = array_merge($this->parged['where'], $where);
        }

        $limit = $this->parged['LIMIT'];
        $offset = $this->parged['OFFSET'];

        if (!$this->baseQuery){
            throw new Exception("Query not initiated yet, are you sure you called the `init` method first?");
        }
        // before applying the offsets, get the total count
        $all = $this->baseQuery->count();
        // apply the limit and offset
        $resultSet = $this->baseQuery
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
        // has previous page
        $hasPrevious = $prevOffset !== null && $offset > 0;

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
