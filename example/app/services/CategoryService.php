<?php

namespace application\services;

use Exception;
use Pionia\Generics\UniversalGenericService;
use Pionia\Response\BaseResponse;
use Porm\Porm;

class CategoryService extends UniversalGenericService
{
    public string $table = 'category';

    public ?array $listColumns = ['id', 'name', 'created_at'];

    public bool $serviceRequiresAuth = false;

    public function heyWorld()
    {
        return BaseResponse::JsonResponse(0, "Hello World");
    }

    /**
     * @throws Exception
     */
    public function getItem(): ?object
    {
        logger->info("Getting item");
        logger->debug("Getting item");
        logger->critical("Getting item");
        logger->error("Getting item");
        logger->warning("Getting item");
        logger->notice("Getting item");
        logger->alert("Getting item");
        logger->emergency("Getting item");
        $data = $this->request->getData();
        $this->requires([$this->pk_field]);
        $id = $data[$this->pk_field];
        $item = Porm::from($this->table)->columns($this->listColumns)->get($id);
        logger->info("Gotten item");
        return $item;
    }
}
