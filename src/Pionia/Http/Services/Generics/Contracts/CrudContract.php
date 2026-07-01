<?php

namespace Pionia\Http\Services\Generics\Contracts;

use Exception;
use Pionia\Exceptions\ValidationException;
use Pionia\Porm\Core\Porm;
use Pionia\Porm\Exceptions\BaseDatabaseException;
use Pionia\Porm\PaginationCore;
use Pionia\Http\UploadedFile;

trait CrudContract
{
    /**
     * Checks if the frontend has defined columns we should query by.
     * Works for both joined and non-joined querying.
     * It pays respect to aliases defined, so, don't forget to respect them too!
     * @return void
     */
    protected function detectAndAddColumns(): void
    {
        if ($this->getFieldValue("dontRelate")) {
            $this->dontRelate = (bool) $this->getFieldValue("dontRelate");
        }

        if ($this->allowClientColumns && ($this->getFieldValue("columns") || $this->getFieldValue("COLUMNS"))) {
            $this->listColumns = $this->getFieldValue("columns") ?? $this->getFieldValue("COLUMNS");
        }

        if ($this->dontRelate) {
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
     * by the time of querying `name(category_name)` will take precedence of `name` thus we shall end up with
     * `category_name` and `id` in the response
     * @return void
     */
    protected function cleanRelationColumns(): void
    {
        if ($this->getListColumns() !== "*") {
            $columns = [];
            foreach ($this->getListColumns() as $column) {
                if (str_contains($column, ".")) {
                    $results = explode(".", $column);
                    $column = $results[1];

                    if (str_contains($column, "(")) {
                        $checker = explode("(", $column);
                        $final = $checker[0];
                        array_map(function ($item) use ($final, &$column) {
                            if (str_starts_with($item, $final)) {
                                $column = null;
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
    protected function getListColumns(): array|string
    {
        return $this->listColumns ?? '*';
    }

    /**
     * Build optional client filter WHERE clauses from whitelisted request fields.
     */
    protected function clientFilterWhere(): array
    {
        if (!$this->allowClientFilters) {
            return [];
        }

        $where = [];
        foreach ($this->request->getData()->all() as $key => $value) {
            if (!is_string($key) || $value === null || $value === '') {
                continue;
            }
            if (in_array($key, ['service', 'action', 'limit', 'offset', 'LIMIT', 'OFFSET', 'pagination', 'PAGINATION', 'search', 'SEARCH', 'columns', 'COLUMNS', 'dontRelate'], true)) {
                continue;
            }
            if ($this->sortableColumns !== [] && in_array($key, $this->sortableColumns, true)) {
                continue;
            }
            $where[$key] = $value;
        }

        return $where;
    }

    /**
     * Apply client sort when sortableColumns is configured.
     */
    protected function applyClientSort(Porm|\Pionia\Porm\Database\Builders\Builder|\Pionia\Porm\Database\Builders\Join $query): mixed
    {
        $orderBy = $this->getFieldValue('orderBy') ?? $this->getFieldValue('ORDER_BY');
        if ($orderBy === null || $this->sortableColumns === []) {
            return $query;
        }

        if (is_string($orderBy) && in_array($orderBy, $this->sortableColumns, true)) {
            return $query->orderBy($orderBy);
        }

        if (is_array($orderBy)) {
            $safe = [];
            foreach ($orderBy as $column => $direction) {
                if (is_int($column) && is_string($direction) && in_array($direction, $this->sortableColumns, true)) {
                    $safe[$direction] = 'ASC';
                } elseif (is_string($column) && in_array($column, $this->sortableColumns, true)) {
                    $safe[$column] = is_string($direction) ? strtoupper($direction) : 'ASC';
                }
            }
            if ($safe !== []) {
                return $query->orderBy($safe);
            }
        }

        return $query;
    }

    /**
     * Detects if we have pagination params anywhere in the request.
     * Pagination kicks in when limit is defined; offset defaults to 0.
     */
    protected function _checkPaginationInternal(array $data): bool
    {
        return (isset($data['limit']) && is_numeric($data['limit']))
            || (isset($data['LIMIT']) && is_numeric($data['LIMIT']));
    }

    /**
     * Detect if our pagination params are defined anywhere in the request.
     */
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

        $id = $this->getFieldValue($this->primaryKey());

        if ($this->cacheRetrieveTtl !== null && $id !== null) {
            $cached = $this->getCache($this->retrieveCacheKey($id), exact: true);
            if ($cached !== null) {
                return is_object($cached) ? $cached : (object) $cached;
            }
        }

        if ($this->weShouldJoin()) {
            $result = $this->getOneJoined();
        } else {
            $id = $id ?? throw new ValidationException("Field {$this->primaryKey()} is required");
            $result = $this->getOneInternal($id, skipCustomHook: true);
        }

        if ($this->cacheRetrieveTtl !== null && $result !== null && $id !== null) {
            $this->setCache($this->retrieveCacheKey($id), $result, $this->cacheRetrieveTtl, exact: true);
        }

        return $result;
    }

    /**
     * Gets one item from the database. Can be overridden by defining a getOne method in the service
     * @throws Exception
     */
    protected function getOneInternal($id, bool $skipCustomHook = false): null | array | object
    {
        if (!$skipCustomHook) {
            $customQueried = $this->getItem();
            if ($customQueried) {
                return $customQueried;
            }
        }

        return $this->query()
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
        if ($this->getItems()) {
            return $this->getItems();
        }

        if ($this->weShouldJoin()) {
            return $this->getAllItemsJoined();
        }

        $query = $this->query()
            ->columns($this->getListColumns())
            ->filter($this->clientFilterWhere());

        $query = $this->applyClientSort($query);

        if ($this->hasLimit()) {
            $query->limit((int) $this->hasLimit());
        } else {
            $query->limit($this->maxListRows);
        }

        if ($this->hasOffset()) {
            $query->startAt((int) $this->hasOffset());
        }

        return $query->all();
    }

    protected function isInJoinMode(): bool
    {
        return $this->joins !== null && count($this->joins) > 0;
    }

    /**
     * Delete in CRUD
     * Handles both post and pre delete events
     * Deletes in joined mode if defined too, but will delete only from the main table
     * @throws Exception
     */
    protected function deleteItem(): mixed
    {
        $pk = $this->pk_field;
        if ($this->isInJoinMode()) {
            $pk = str_contains($this->pk_field, ".") ? explode(".", $this->pk_field)[1] : $this->pk_field;
        }

        $id = $this->getFieldValue($this->pk_field) ?? $this->getFieldValue($pk) ?? throw new Exception("Field {$this->pk_field} is required");
        $item = $this->getOneInternal($id, skipCustomHook: true);

        if (!$item) {
            throw new Exception("Record with $this->pk_field $id not found");
        }
        $deleted = null;
        if ($this->preDelete($item)) {
            $this->query()->inTransaction(function () use ($id, $pk, &$deleted) {
                $deleted = $this->query()->delete([$pk => $id]);
            });

            return $this->postDelete($deleted, $item);
        }

        return null;
    }

    /**
     * @throws Exception
     */
    protected function checkIfFieldPassesAllValidations(string $column, bool $required = true): mixed
    {
        $dt = $this->getFieldValue($column);
        if ($required && $dt === null) {
            throw new ValidationException("Field {$column} is required");
        }

        if ($dt instanceof UploadedFile) {
            $dt = $this->handleUpload($dt, $column);
        }

        return $dt;
    }

    private function listCacheKey(): string
    {
        return 'list_' . md5(json_encode([
            $this->table,
            $this->connection,
            $this->baseAlias,
            $this->getListColumns(),
            $this->clientFilterWhere(),
            $this->hasLimit(),
            $this->hasOffset(),
            $this->dontRelate,
            $this->request->getData()->all(),
        ], JSON_THROW_ON_ERROR));
    }

    private function retrieveCacheKey(mixed $id): string
    {
        return 'retrieve_' . md5(json_encode([
            $this->table,
            $this->connection,
            $this->baseAlias,
            $id,
            $this->getListColumns(),
            $this->dontRelate,
        ], JSON_THROW_ON_ERROR));
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
            throw new Exception("Create columns undefined!");
        }

        $sanitizedData = [];
        foreach ($this->createColumns as $column) {
            $required = true;
            if (str_ends_with($column, "?")) {
                $column = trim(str_replace("?", "", $column));
                $required = false;
            }
            $dt = $this->checkIfFieldPassesAllValidations($column, $required);
            if ($dt !== null) {
                $sanitizedData[$column] = $dt;
            }
        }

        $saved = null;
        if ($toSave = $this->preCreate($sanitizedData)) {
            $this->query()->inTransaction(function () use (&$saved, $toSave) {
                $saved = $this->query()->save($toSave);
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
        $paginator = new PaginationCore(
            reqData: $this->request->getData()->all(),
            table: $this->table,
            limit: $this->limit,
            offset: $this->offset,
            db: $this->connection,
            alias: $this->baseAlias,
        );
        $prep1 = $paginator->columns($this->getListColumns());

        if ($filters = $this->clientFilterWhere()) {
            $prep1->where($filters);
        }

        $prep1->init(function (Porm $query) {
            if ($this->weShouldJoin()) {
                $join = $query->join();
                $join = $this->attachJoins($join);

                return $this->applyClientSort($join);
            }
            $builder = $query->filter();

            return $this->applyClientSort($builder);
        });

        if ($this->approximatePagination) {
            return $prep1->paginateApproximate();
        }

        return $prep1->paginate();
    }

    /**
     * Retrieve all with pagination
     * @throws Exception
     */
    protected function getAllWithPagination(): ?array
    {
        $this->detectAndAddColumns();
        $data = $this->request->getData()->all();
        if ($this->detectPagination($data)) {
            if ($this->cacheListTtl !== null) {
                $cached = $this->getCache($this->listCacheKey(), exact: true);
                if ($cached !== null) {
                    return $cached;
                }
            }

            $page = $this->paginate();

            if ($this->cacheListTtl !== null && $page !== null) {
                $this->setCache($this->listCacheKey(), $page, $this->cacheListTtl, exact: true);
            }

            return $page;
        }

        return $this->allItems();
    }

    /**
     * @throws Exception
     */
    protected function randomItem()
    {
        $this->detectAndAddColumns();
        $limit = (int) ($this->getFieldValue('limit') ?? $this->getFieldValue('size') ?? 1);
        $where = $this->clientFilterWhere();

        if ($this->weShouldJoin()) {
            $query = $this->attachJoins();
            if ($where !== []) {
                $query->where($where);
            }

            return $query->random($limit);
        }

        return $this->query()
            ->columns($this->getListColumns())
            ->random($limit, $where !== [] ? $where : null);
    }

    /**
     * Returns the primary key field
     * @throws Exception
     */
    protected function primaryKey(): bool|string
    {
        if ($this->isInJoinMode() && $this->pk_field) {
            return str_contains($this->pk_field, ".") ? explode(".", $this->pk_field)[1] : $this->pk_field;
        }

        return $this->pk_field;
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
        $id = $this->getFieldValue($this->primaryKey()) ?? throw new ValidationException("Field {$this->primaryKey()} is required");

        if ($this->skipUpdatePrefetch) {
            $toArray = [$this->primaryKey() => $id];
        } else {
            $item = $this->query()->get($id, $this->primaryKey());

            if (!$item) {
                throw new ValidationException("Record with id {$id} not found");
            }

            $toArray = is_array($item) ? $item : (array) $item;
        }

        if ($this->updateColumns) {
            foreach ($this->updateColumns as $column) {
                $optional = false;
                if (str_ends_with($column, "?")) {
                    $column = trim(str_replace("?", "", $column));
                    $optional = true;
                }
                if (!$optional && $this->getFieldValue($column) === null) {
                    continue;
                }
                if ($this->getFieldValue($column) !== null || $optional) {
                    $dt = $this->checkIfFieldPassesAllValidations($column, !$optional);
                    if ($dt !== null || $optional) {
                        $toArray[$column] = $dt;
                    }
                }
            }
        } else {
            foreach ($toArray as $key => $value) {
                if ($key === $this->primaryKey()) {
                    continue;
                }
                if ($this->getFieldValue($key) !== null) {
                    $dt = $this->checkIfFieldPassesAllValidations($key, false);
                    if ($dt !== null) {
                        $toArray[$key] = $dt;
                    }
                }
            }
        }
        $updated = null;
        if ($toSave = $this->preUpdate($toArray)) {
            $this->query()->inTransaction(function () use ($toSave, $id) {
                $this->query()->update($toSave, [$this->primaryKey() => $id]);
            });
            $updated = $this->getOneInternal($id, skipCustomHook: true);
        }
        if (!$updated) {
            throw new Exception("Update failed for record with id $id");
        }

        return $this->postUpdate($updated);
    }
}
