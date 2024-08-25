<?php

namespace Pionia\Pionia\Middlewares;

use Pionia\Pionia\Utils\Arrayable;

trait MiddlewareTrait
{

    /**
     * Only those services that are listed here will trigger the middleware to run
     * @return Arrayable
     */
    public function limitServicesTo(): Arrayable
    {
        return new Arrayable([]);
    }

    /**
     * This hook is called before the middleware runs against the request.
     */
    public function beforeRequest()
    {
    }

    /**
     * If provided, this hook will be called after the middleware runs against the request.
     */
    public function afterRequest()
    {
    }
    /**
     * This method is called before the middleware runs against the response.
     */
    public function beforeResponse()
    {
    }

    /**
     * If provided, this hook is called after the middleware runs against the response.
     */
    public function afterResponse()
    {
    }


}
