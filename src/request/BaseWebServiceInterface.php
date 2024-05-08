<?php

namespace jetPhp\request;

use jetPhp\response\BaseResponse;

interface BaseWebServiceInterface
{
    /**
     * This is the actual switcher. It maps the action from the request to a method in your service(class)
     *
     * Sample implementation is as below
     * @param string $action This is the action to be performed
     * @param Request $request This is the request object
     * @return BaseResponse
     */
    public function runAction(string $action, Request $request): BaseResponse;

    /**
     * This is the main method that processes the request.
     *
     * @param string $action This is the action to be performed
     * @param mixed $request This is the request object
     * @return BaseResponse The uniform response object that is returned by the framework
     */
    public function process(string $action, Request $request): BaseResponse;
}
