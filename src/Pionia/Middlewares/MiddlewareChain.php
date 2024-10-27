<?php

namespace Pionia\Middlewares;

use Exception;
use Pionia\Base\PioniaApplication;
use Pionia\Collections\Arrayable;
use Pionia\Contracts\MiddlewareContract;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\Events\PostMiddlewareChainRunEvent;
use Pionia\Middlewares\Events\PreMiddlewareChainRunEvent;
use Pionia\Utils\Containable;
use Pionia\Utils\Microable;
use Pionia\Utils\Support;

class MiddlewareChain
{

    use Microable, Containable;

    /**
     * @var PioniaApplication
     */
    private PioniaApplication $app;

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

    public function addAll(array | Arrayable $middlewares)
    {
        return $this->middlewareContainer->merge($middlewares);
    }

    public function all()
    {
        return $this->middlewareContainer->all();
    }

    public function __construct(PioniaApplication $app)
    {
        $this->app = $app;
        $this->context = $app->context;
        $this->middlewareContainer = $this->getOrDefault('middlewares', new Arrayable([]));
    }

    /**
     * Get the middleware chain
     *
     * @return array
     */
    public function get(): array
    {
        return $this->middlewareContainer->all();
    }

    /**
     * @return null|Arrayable
     */
    public function middlewareStack(): ?Arrayable
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
        $this->context->set('middlewares', $this->middlewareContainer);
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
            $this->context->set('middlewares', $this->middlewareContainer);
        } else {
            throw new Exception("Middleware must be implementing MiddlewareContract or extending Middleware");
        }
        return $this;
    }

    /**
     * Run the middleware chain
     *
     * Dispatches events before and after the middleware chain is run
     *
     * @param Request $request
     * @param ?Response $response
     */
    public function handle(Request $request, ?Response $response = null): void
    {
        if ($response){
            if ($this->app->dispatcher) {
                $this->app->dispatcher->dispatch(new PostMiddlewareChainRunEvent($this));
            }
            $this->rail($request, $response);

            if ($this->app->dispatcher) {
                $this->app->dispatcher->dispatch(new PostMiddlewareChainRunEvent($this));
            }
        }else {
            if ($this->app->dispatcher) {
                $this->app->dispatcher->dispatch(new PreMiddlewareChainRunEvent($this));
            }
            $this->rail($request);
        }
    }

    /**
     * Run the middleware chain
     *
     * @param Request $request
     * @param ?Response $response
     */
    public function rail(Request $request, ?Response $response = null): void
    {
        $current = $this->middlewareContainer->shift();
        if (!$current) {
            return;
        }
        if ($this->isAMiddleware($current)) {
            $firstMiddleware = $this->asMiddleware($current);
            $firstMiddleware->execute($request, $response, $this);
        } else {
            $this->next($request, $response, $this);
        }

    }

    /**
     * Run the next middleware in the chain
     */
    public function next(Request $request, ?Response $response, MiddlewareChain $chain): void
    {
        $chain->rail($request, $response);
    }


    public function isAMiddleware($class): bool
    {
        return $class && (Support::implements($class, MiddlewareContract::class) || Support::extends($class, Middleware::class));
    }

    private function asMiddleware($klass): MiddlewareContract
    {
        return new $klass();
    }


}
