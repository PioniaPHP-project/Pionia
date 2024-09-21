<?php

/**
 * This service is auto-generated from pionia cli.
 * Remember to register this service in any of your available switches.
 */

namespace Application\Services;

use Pionia\Collections\Arrayable;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Services\Service;
use Symfony\Component\HttpFoundation\FileBag;

class AuthService extends Service
{
	/**
	 * getAuthAction action
	 */
	protected function getAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached get_auth_action action');
	}


	/**
	 * createAuthAction action
	 */
	protected function createAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached create_auth_action action');
	}


	/**
	 * listAuthAction action
	 */
	protected function listAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached list_auth_action action');
	}


	/**
	 * deleteAuthAction action
	 */
	protected function deleteAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached delete_auth_action action');
	}


	/**
	 * updateAuthAction action
	 */
	protected function updateAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached update_auth_action action');
	}
}
