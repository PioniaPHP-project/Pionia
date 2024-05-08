<?php

namespace jetPhp\request;

use jetPhp\exceptions\ResourceNotFoundException;
use jetPhp\response\BaseResponse;

/**
 * This is the main class all other services must extend.
 *
 * All repetitive code is abstracted here.
 *
 * Once extended, it requires one to implement the runAction method.
 */
abstract class BaseRestService
{

    use AuthTrait;
    use RequestActionTrait;

    public Request $request;

    abstract public static function registerActions(): array;

    /**
     * @throws ResourceNotFoundException
     */
    public function process(string $action, Request $request): BaseResponse
    {
        $this->request = $request;
        $service = $request->getData()['SERVICE'];

        $data = $this->request->getData();
        $files = $this->request->files;

        $actions = $this::registerActions();
        if (array_key_exists($action, $actions)) {
            $action = $actions[$action];
            if (!method_exists($this, $action)) {
                throw new ResourceNotFoundException("Action $action is not a valid action");
            }
            return $this->$action($data, $files, $request);
        }
        throw new ResourceNotFoundException("Action $action not found in the $service context");
    }
}
