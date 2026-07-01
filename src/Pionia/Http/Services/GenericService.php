<?php

namespace Pionia\Http\Services;

use Exception;
use Pionia\Http\UploadedFile;
use Pionia\Http\Services\Generics\Contracts\CrudContract;
use Pionia\Http\Services\Generics\Contracts\EventsContract;
use Pionia\Http\Services\Generics\Contracts\JoinContract;
use Pionia\Http\Services\Generics\Contracts\UploadsContract;
use Pionia\Porm\Core\Porm;

abstract class GenericService extends Service
{
    /**
     * @var string The base table to be used in the service. This is required when in joins or not
     */
    public string $table;

    /**
     * SQL alias for the base table in join queries (e.g. `st` for `sample_table AS st`).
     */
    public ?string $baseAlias = null;

    /**
     * Can be mutated by the frontend to turn off relationships and go back to querying the base table again.
     */
    private bool $dontRelate = false;

    /**
     * @var int The initial number of items to return per `list` request can de overridden in the request.
     */
    public int $limit = 10;

    /**
     * @var int The initial index to start from, can de overridden in the request.
     */
    public int $offset = 0;

    /**
     * Maximum rows returned when list is called without explicit pagination or limit.
     */
    public int $maxListRows = 100;

    /**
     * When true, clients may override list columns via `columns` / `COLUMNS` in the request.
     */
    public bool $allowClientColumns = false;

    /**
     * When true, clients may pass whitelisted filter fields from the request root.
     */
    public bool $allowClientFilters = false;

    /**
     * Columns clients may sort by when `orderBy` / `ORDER_BY` is sent in the request.
     *
     * @var array<int, string>
     */
    public array $sortableColumns = [];

    /**
     * @var string The primary key field name. Default is `id`.
     */
    public string $pk_field = 'id';

    /**
     * @var ?string The connection to use. Default is `db`.
     */
    public ?string $connection = null;

    /**
     * @var array|null The columns to return in all requests returning data.
     */
    public ?array  $listColumns = null;

    /**
     * @var array|null The columns to return in the `create` request. Only these columns will be populated from
     * the request and saved
     */
    public ?array $createColumns = null;

    /**
     * @var array|null The columns to return in the `update` request. Only these columns will be populated from
     * the request and saved. If left null, all columns defined in the request object will be updated.
     */
    public ?array $updateColumns = null;

    /**
     * Define columns that should be received as files in this array.
     * @var array|null
     */
    public ?array $fileColumns = null;

    use EventsContract, CrudContract, JoinContract, UploadsContract;

    /**
     * Start a Porm query against this service table, alias, and connection.
     */
    protected function query(): Porm
    {
        return table($this->table, $this->baseAlias, $this->connection);
    }

    /**
     * Picks the value of a field from the request data
     * @param $name
     * @return mixed|UploadedFile|null
     */
    protected function getFieldValue($name): mixed
    {
        if ($this->fileColumns && in_array($name, $this->fileColumns, true)) {
            return $this->request->getFileByName($name) ?? null;
        }

        return $this->request->getData()->get($name);
    }

    /**
     * Provides the default upload behaviour for the service.
     *
     * You can override this method in your service to provide custom upload behaviour.
     * @param UploadedFile $file The file to upload
     * @param string $fileName The name to save the file as
     * @throws Exception
     */
    public function handleUpload(UploadedFile $file, string $fileName): mixed
    {
        return $this->defaultUpload($file, $fileName);
    }

}
