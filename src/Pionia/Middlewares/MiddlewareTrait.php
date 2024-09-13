<?php

namespace Pionia\Middlewares;

use Pionia\Collections\Arrayable;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;

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

    /**
     * This method is called to run the middleware. Every middleware must implement this method.
     *
     * @param Request $request - The request object
     * @param Response|null $response - The response object
     * @param MiddlewareChain $chain
     */
    public function execute(Request $request, ?Response $response, MiddlewareChain $chain): void
    {
        if (!$this->switchedOff) {
            $data = $request->getData();

            $this->services = $this->limitServicesTo();

            $service = $data->get('service');

            if ($this->services->isFilled()) {
                if ($this->services->has($service)) {
                    $this->_runMiddleware($request, $response);
                }
            } else {
                $this->_runMiddleware($request, $response);
            }
        }

        $chain->next($request, $response, $chain);
    }

    private function _runMiddleware($request, $response): void
    {
        if ($response){
            $this->beforeResponse();

            $this->onResponse($response);

            $this->afterResponse();
        } else {
            $this->beforeRequest();

            $this->onRequest($request);

            $this->afterRequest();
        }
    }


}
