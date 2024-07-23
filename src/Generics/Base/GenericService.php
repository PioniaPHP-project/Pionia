<?php

namespace Pionia\Generics\Base;

use Pionia\Request\BaseRestService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;

class GenericService extends BaseRestService
{
    /**
     * @var string The base table to be used in the service. This is required when in joins or not
     */
    public string $table;

    /**
     * Can be mutated by the frontend to turn off relationships and go back to querying the base table again.
     * @var bool
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
     * @var string The primary key field name. Default is `id`.
     */
    public string $pk_field = 'id';

    /**
     * @var string The connection to use. Default is `db`.
     */
    public string $connection = 'db';

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
     * @param $name
     * @return mixed|UploadedFile|FileBag|null
     */
    private function getFieldValue($name): mixed
    {
        if (isset($this->fileColumns[$name])){
            return $this->request->getFileByName($name) ?? null;
        }
        $data = $this->request->getData();
        return $data[$name] ?? null;
    }
}
