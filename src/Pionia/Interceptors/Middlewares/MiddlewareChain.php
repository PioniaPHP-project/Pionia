<?php

namespace Pionia\Pionia\Interceptors\Middlewares;

use Exception;
use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Base\Utils\Microable;
use Pionia\Pionia\Contracts\MiddlewareInterface;
use Pionia\Pionia\Utilities\Arrayable;
use Pionia\Request\Request;
use Pionia\Response\Response;

class MiddlewareChain
{

    use Microable;

    private Arrayable $middlewareContainer;
    /**
     * Add a middleware to the middleware chain
     *
     * @param string $middleware
     * @return MiddlewareChain
     */
    public function add(string $middleware): static
    {
        $this->middlewareContainer->add($middleware);
        return $this;
    }

    public function __construct(PioniaApplication $app)
    {
        $this->middlewareContainer = $app->context->get('middlewares');
    }

    /**
     * Get the middleware chain
     *
     * @return Arrayable
     */
    public function get(): Arrayable
    {
        return $this->middlewareContainer;
    }

    /**
     * Add a middleware to the middleware chain after a specific middleware
     *
     * @param string $middlewareSearch The target middleware in the chain
     * @param string $middlewareToInsert The new middleware we are registering
     * @return MiddlewareChain
     */
    public function addAfter(string $middlewareSearch, string $middlewareToInsert): static
    {
        $this->middlewareContainer->addAfter($middlewareSearch, $middlewareToInsert);
        return $this;
    }

    /**
     * Add a middleware before another middleware
     *
     * @param string $middlewareSearch The target middleware in the chain
     * @param string $middlewareToInsert The new middleware we are registering
     * @return MiddlewareChain
     * @throws Exception
     */
    public function addBefore(string $middlewareSearch, string $middlewareToInsert): static
    {
        if ($this->isAMiddleware($middlewareToInsert)) {
            $this->middlewareContainer->addBefore($middlewareSearch, $middlewareToInsert);
        } else {
            throw new Exception("Middleware must be implementing MiddlewareInterface or extending Middleware");
        }
        return $this;
    }

    /**
     * Run the middleware chain
     *
     * @param Request $request
     * @param Response $response
     */
    public function rail(Request $request, Response $response): void
    {
        $first = $this->middlewareContainer->shift();
        if ($this->isAMiddleware($first)) {
            $firstMiddleware = $this->toMiddlewareClass($first);
            $firstMiddleware->execute($request, $response, $this);
        } else {
            $this->next($request, $response, $this);
        }

    }

    /**
     * Run the next middleware in the chain
     */
    public function next(Request $request, Response $response, MiddlewareChain $chain): void
    {
        $chain->rail($request, $response);
    }


    private function isAMiddleware($class): bool
    {
        if (is_subclass_of($class, Middleware::class) || is_subclass_of($class, MiddlewareInterface::class)) {
            return true;
        }

        return false;
    }

    private function toMiddlewareClass($klass): Middleware
    {
        return new $klass();
    }


}
