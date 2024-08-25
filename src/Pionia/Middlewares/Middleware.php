<?php

namespace Pionia\Pionia\Middlewares;

use Pionia\Pionia\Contracts\MiddlewareContract;
use Pionia\Pionia\Http\Request\Request;
use Pionia\Pionia\Http\Response\Response;
use Pionia\Pionia\Utils\Arrayable;

abstract class Middleware implements MiddlewareContract
{
  private Arrayable $services;

  private bool $switchedOff = false;

  use MiddlewareTrait;

  public function __construct()
  {
    $this->services = new Arrayable([]);
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
