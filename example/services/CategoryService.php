<?php

namespace Application\Services;

use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Services\Generics\RetrieveListCreateService;
use Pionia\Http\Services\JoinType;

class CategoryService extends RetrieveListCreateService
{
    public string $table = 'user';
    public string $pk_field = 'id';
    public ?array $joins = [
        'role' => ['role_id' => 'id'],
    ];
    public ?array $joinTypes = [
        'sub_category' => JoinType::INNER,
    ];

    public function testAction(): BaseResponse
    {
        $this->request->getData();
        return response(0, 'Hello World');
    }
}
