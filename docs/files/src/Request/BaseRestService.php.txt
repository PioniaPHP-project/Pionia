<?php

namespace JetPhp\Request;

use JetPhp\Response\BaseResponse;

/**
 * This is the main class all other services must extend.
 *
 * All repetitive code is abstracted here.
 *
 * Once extended, it requires one to implement the runAction method.
 */
abstract class BaseRestService implements BaseWebServiceInterface
{

    use AuthTrait;
    use RequestActionTrait;

    /**
     * @inheritDoc
     */
    public function process(string $action_or_service, Request $request): BaseResponse
    {
        return $this->runAction($action_or_service, $request);
    }

}
