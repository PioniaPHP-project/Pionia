<?php

/**
 * Company records — custom actions beyond generic CRUD.
 *
 * @moonlight-service category
 * @moonlight-version v1
 * @moonlight-table company
 */
namespace Application\Services;

use Exception;
use Pionia\Collections\Arrayable;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Services\Service;
use Pionia\Http\Bag\FileBag;
use Throwable;

class CategoryService extends Service
{
    /**
     * @moonlight-action update
     * @moonlight-summary Update a company by id
     * @moonlight-param int id Row id
     * @moonlight-param string name New name
     * @moonlight-example {"service":"category","action":"update","id":1,"name":"Acme"}
     */
	protected function updateAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
        $id = $data->get('id');
        $name = $data->getOrThrow('name', 'Name is required');
        db('company')->update(['name' => $name], $id);

		return response(0, 'Company updated', db('company')->get($id));
	}

    /**
     * @moonlight-action list
     * @moonlight-summary List all companies
     * @moonlight-example {"service":"category","action":"list"}
     */
    protected function listAction(Arrayable $request): BaseResponse
    {
        defer(function () {
            logger()->info('category.list: deferred log (after response sent to client)');
        });

        return response(0, null, db('company')->all());
    }

    /**
     * @moonlight-action save_or_update
     * @moonlight-summary Create or update a company row
     * @moonlight-param object data Row payload (name, optional id)
     * @moonlight-example {"service":"category","action":"save_or_update","data":{"name":"Acme"}}
     */
    protected function saveOrUpdateAction(Arrayable $request): BaseResponse
    {
        $data = $request->get('data');
        $saved = db('company')->saveOrUpdate($data);

        return response(0, null, $saved);
    }
}
