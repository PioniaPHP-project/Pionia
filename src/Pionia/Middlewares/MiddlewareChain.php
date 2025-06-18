<?php

namespace Pionia\Middlewares;

use Exception;
use Pionia\Collections\Arrayable;
use Pionia\Contracts\MiddlewareContract;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\Events\PostMiddlewareChainRunEvent;
use Pionia\Middlewares\Events\PreMiddlewareChainRunEvent;
use Pionia\Utils\Microable;
use Pionia\Utils\Support;

class MiddlewareChain
{

    use Microable;

    private Arrayable $middlewareContainer;
    private Arrayable $middlewareStackCopy;
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

    public function __construct()
    {
        $this->middlewareContainer = app()->getOrDefault(app()::MIDDLEWARE_TAG, new Arrayable([]));
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
        return $this->update();
    }

    private function update(): static
    {
        app()->set(app()::MIDDLEWARE_TAG, $this->middlewareContainer);
        app()->updateCache('app_middlewares', $this->middlewareContainer->all(), true, 200);
        return $this;
    }

    /**
     * Add middleware before another middleware
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
            throw new Exception("Middleware must be implementing MiddlewareContract or extending Middleware");
        }
        return $this->update();
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
        $copy = clone $this->middlewareContainer;
        // we need to take a snapshot of the middleware stack so that we can run the chain multiple times
        $this->middlewareStackCopy = $copy;
        if ($response){
            event(new PostMiddlewareChainRunEvent($this), PostMiddlewareChainRunEvent::name());
            $this->rail($request, $response);

            event(new PostMiddlewareChainRunEvent($this), PostMiddlewareChainRunEvent::name());
        } else {
            event(new PreMiddlewareChainRunEvent($this), PreMiddlewareChainRunEvent::name());
            $this->rail($request);
        }
    }

    /**
     * Run the middleware chain
     *
     * @param Request $request
     * @param ?Response $response
     */
    private function rail(Request $request, ?Response $response = null): void
    {
        $current = $this->middlewareStackCopy->shift();
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
