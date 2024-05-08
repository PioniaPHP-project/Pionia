<?php

namespace jetPhp\database;


use jetPhp\exceptions\LimitPaginationException;
use jetPhp\exceptions\OffsetPaginationException;

/**
 *  This class helps us to perform paginated queries from the database. The fast that counting and querying happens
 *  in the db, it makes this somewhat optimal.
 *
 *  Please note that the query provided must not be limited using LIMIT or offset using OFFSET.
 *
 *  If you are not intending to return the values instantly, you can use the method $this->pager() to
 *  just paginate and return the class object for further actions.
 *
 *  Note that, if your query mentions some bindings, please pass them in the pager($your_bindings_array_here) or paginate($your_bindings_array_here)
 *  not in the constructor.
 *
 * @example
 *
 *  ```php
 *
 * $paginated = new \Jet\RestPhp\Schemas\Paginator('Select * from notification_receiver where receiver_type = :type');
 * $new = $paginated->startFrom(20)
 *          ->Using('db2')
 *          ->LimitBy(10)
 *          ->paginate(['type' => 'USER']);
 * ```
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class Paginator extends QueryBuilder
{
    protected string $query;
    protected int $limit = 50;
    protected int $offset = 0;
    public int | null $next_offset = null;
    public array | null $results = null;
    public int | null $previous_offset= null;
    public bool $has_next_page = true;
    public bool $has_prev_page = false;
    public int | null $number_of_records = null;
    public int | null $total_records = null;
    public function __construct(string $query)
    {
        parent::__construct();
        $this->query = $query;
    }

    public function LimitBy($limit)
    {
        $this->limit =  $limit;
        return $this;
    }

    public function startFrom($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function paginate($bindings = [])
    {
        if (str_contains($this->query, 'LIMIT') || str_contains($this->query, 'limit')) {
            throw new LimitPaginationException('Queries that already limit cannot be paginated');
        } elseif (str_contains(trim($this->query), 'OFFSET') || str_contains($this->query, 'offset')) {
            throw new OffsetPaginationException('Queries that already offset cannot be paginated');
        }

        // first query will count * records we have
        $this->total_records = $this->_count_internal($this->query, $bindings);

        // apply the limits and query again
        // mysql has the tendency of using LIMIT before OFFSET, so we have to switch the order
        // but other dbs don't care.
        $query_for_records = $this->query  . ' LIMIT ' . $this->limit. ' OFFSET ' . $this->offset;


        $data = $this->all($query_for_records, $bindings);
        $this->number_of_records = count($data);
        $this->results = $data;

        // we are at the start, we can't have previous
        if ($this->offset != 0){
            $this->has_prev_page = true;
        }
        // we have even failed to reach the limit, we ran out of items
        if ($this->number_of_records < $this->limit){
            $this->has_next_page = false;
        } else {
            $this->next_offset = $this->offset + $this->limit;
        }

        if ($this->offset == 0){
            $this->previous_offset = null;
        } else{
            $this->previous_offset = $this->offset - $this->limit;
        }

        if ($this->previous_offset < 0 ){
            $this->previous_offset = 0;
        }


        return [
            'total_records' => $this->total_records,
            'results' => $this->results,
            'next_offset' => $this->next_offset,
            'previous_offset' => $this->previous_offset,
            'has_next_page' => $this->has_next_page,
            'has_prev_page' => $this->has_prev_page,
            'number_of_records' => $this->number_of_records,
        ];
    }

    /**
     * We use this method if we still want to play with the class object
     *
     * To return instant results please refer to or call ```php $this->paginate()``` above
     *
     * @param $bindings
     * @return $this
     * @throws LimitPaginationException
     * @throws OffsetPaginationException
     */
    public function pager($bindings = [])
    {
        $this->paginate($bindings);
        return $this;
    }

}
