<?php

/**
 * Category and skill management — custom actions beyond generic CRUD.
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
use Symfony\Component\HttpFoundation\FileBag;
use Throwable;

class CategoryService extends Service
{
    /**
     * Update a company/category row by id.
     *
     * @moonlight-action update
     * @moonlight-summary Updates a company record
     * @moonlight-param int id Row id
     * @moonlight-param string name New name
     * @moonlight-return object updated row in returnData
     * @moonlight-example {"service":"category","action":"update","id":1,"name":"Acme"}
     * @throws Exception
     * @throws Throwable
     */
	protected function updateAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
        $id = $data->get('id');
        $name = $data->getOrThrow('name', 'Name is required');
        db("company")->update(['name' => $name], $id);
		return response(0, 'You have reached update_category_action action', db("company")->get($id));
	}

    /**
     * List skills linked to categories.
     *
     * @moonlight-action list
     * @moonlight-summary Lists skill rows
     * @moonlight-return array items in returnData
     * @moonlight-example {"service":"category","action":"list"}
     * @throws Exception
     */
    protected function listAction(Arrayable $request): BaseResponse
    {
        return response(0,
            'You have reached list_company_action action', db("skill")->all());
    }

    /**
     * Fetch a single skill by id.
     *
     * @moonlight-action bulk
     * @moonlight-summary Returns one skill row by id
     * @moonlight-param int id Skill id
     * @moonlight-example {"service":"category","action":"bulk","id":1}
     * @throws Throwable
     */
    protected function bulkAction(Arrayable $request): BaseResponse
    {
        $id = $request->get('id');
        $saved = db('skill')->getOrThrow($id, 'Skill not found');
        return response(0, 'You have reached bulk_save action', $saved);
    }

    /**
     * Create or update a skill row.
     *
     * @moonlight-action save_or_update
     * @moonlight-summary Upserts a skill record
     * @moonlight-param object data Row payload
     * @moonlight-example {"service":"category","action":"save_or_update","data":{"name":"PHP"}}
     * @throws Exception
     */
    protected function saveOrUpdateAction(Arrayable $request): BaseResponse
    {
        $data = $request->get('data');
        $saved = db('skill')->saveOrUpdate($data);
        return response(0, null, $saved);
    }
}
