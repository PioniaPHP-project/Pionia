<?php

/**
 * Authentication demo — CRUD-style auth records.
 *
 * @moonlight-service auth
 * @moonlight-version v1
 * @moonlight-auth partial
 */
namespace Application\Services;

use Pionia\Collections\Arrayable;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Services\Service;
use Pionia\Http\Bag\FileBag;

class AuthService extends Service
{
	/**
	 * Return the current authenticated user context.
	 *
	 * @moonlight-action get_auth
	 * @moonlight-summary Returns auth context for the current request
	 * @moonlight-auth required
	 * @moonlight-example {"service":"auth","action":"get_auth"}
	 */
	protected function getAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached get_auth_action action');
	}

	/**
	 * Create a new auth record.
	 *
	 * @moonlight-action create_auth
	 * @moonlight-summary Creates an auth record
	 * @moonlight-auth required
	 * @moonlight-param string username Login name
	 * @moonlight-param string password Secret
	 * @moonlight-example {"service":"auth","action":"create_auth","username":"demo","password":"secret"}
	 */
	protected function createAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached create_auth_action action');
	}

	/**
	 * List auth records.
	 *
	 * @moonlight-action list_auth
	 * @moonlight-summary Lists auth records (demo stub)
	 * @moonlight-auth none
	 * @moonlight-example {"service":"auth","action":"list_auth"}
	 */
	protected function listAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached list_auth_action action');
	}

	/**
	 * Delete an auth record by id.
	 *
	 * @moonlight-action delete_auth
	 * @moonlight-summary Deletes an auth record
	 * @moonlight-auth required
	 * @moonlight-param int id Record id
	 * @moonlight-example {"service":"auth","action":"delete_auth","id":1}
	 */
	protected function deleteAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached delete_auth_action action');
	}

	/**
	 * Update an auth record.
	 *
	 * @moonlight-action update_auth
	 * @moonlight-summary Updates an auth record
	 * @moonlight-auth required
	 * @moonlight-param int id Record id
	 * @moonlight-example {"service":"auth","action":"update_auth","id":1}
	 */
	protected function updateAuthAction(Arrayable $data, ?FileBag $files = null): BaseResponse
	{
		return response(0, 'You have reached update_auth_action action');
	}
}
