<?php

/**
 * This service is auto-generated from pionia cli.
 * Remember to register this service in any of your available switches.
 */

namespace Application\Services;

use Exception;
use Pionia\Pionia\Http\Response\BaseResponse;
use Pionia\Pionia\Http\Services\Service;
use Pionia\Pionia\Utils\Arrayable;
use Symfony\Component\HttpFoundation\FileBag;

class Category3Service extends Service
{
    /**
     * getCategory3Action action
     * @throws Exception
     */
	protected function getCategory3Action(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
        $results = db('company')->all();
		return cachedResponse(
            $this,
            response(
                0,
                'You have reached get_category3_action',
                $results,
            ),
            10
        );
	}


	/**
	 * createCategory3Action action
	 */
	protected function createCategory3Action(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached create_category3_action action');
	}


	/**
	 * listCategory3Action action
	 */
	protected function listCategory3Action(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached list_category3_action action');
	}


	/**
	 * deleteCategory3Action action
	 */
	protected function deleteCategory3Action(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached delete_category3_action action');
	}


	/**
	 * updateCategory3Action action
	 */
	protected function updateCategory3Action(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached update_category3_action action');
	}
}
