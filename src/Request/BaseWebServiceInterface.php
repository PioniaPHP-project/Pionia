<?php

namespace JetPhp\Request;

use JetPhp\Response\BaseResponse;

interface BaseWebServiceInterface
{
    /**
     * This is the actual switcher. It maps the action from the request to a method in your service(class)
     *
     * Sample implementation is as below
     * @param string $action_or_service This is the service or action to be performed
     * @param Request $request This is the request object
     * @return BaseResponse
     */
    public function runAction(string $action_or_service, Request $request): BaseResponse;

    /**
     * This is the main method that processes the request.
     *
     * @param string $action_or_service
     * @param mixed $request This is the request object
     * @return BaseResponse The uniform response object that is returned by the framework
     */
    public function process(string $action_or_service, Request $request): BaseResponse;
}
