<?php

/**
 * This service is auto-generated from pionia cli.
 * Remember to register this service in any of your available switches.
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
     * updateCategoryAction action
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
     * @throws Exception
     */
    protected function listAction(Arrayable $request): BaseResponse
    {

        return response(0,
            'You have reached list_company_action action', db("company")->all());
    }

    /**
     * @throws Throwable
     */
    protected function bulkAction(Arrayable $request): BaseResponse
    {
        $data = $request->getOrThrow('data', 'Data is required');
        logger()->info('Bulk data', $data);
        $saved = db('skill')->saveAll($data);
        return response(0, 'You have reached bulk_save action', $saved);
    }
}
