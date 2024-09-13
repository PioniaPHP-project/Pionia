<?php

namespace Pionia\Http\Base;

use DI\Container;
use Exception;
use Pionia\Auth\AuthenticationChain;
use Pionia\Base\PioniaApplication;
use Pionia\Contracts\KernelContract;
use Pionia\Cors\PioniaCors;
use Pionia\Http\Base\Events\PostSwitchRunEvent;
use Pionia\Http\Base\Events\PreKernelBootEvent;
use Pionia\Http\Base\Events\PreSwitchRunEvent;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Response\Response;
use Pionia\Http\Routing\PioniaRouter;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Utils\Microable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Throwable;

class WebKernel implements KernelContract
{
    use Microable;

    private PioniaApplication $app;

    private PioniaCors $corsWorker;

    private LoggerInterface $logger;

    public function __construct(PioniaApplication $application)
    {
        $this->app = $application;

        $this->logger = $application->logger;

        $this->corsWorker = new PioniaCors($application);
    }

    public function handle(Request $request): Response
    {
        try {
            $this->boot($request);

            $routes = $this->app->getSilently(PioniaRouter::class)->getRoutes();

            // prepare the request for symfony routing
            $controllerResolver = new ControllerResolver($this->logger);
            $argumentResolver = new ArgumentResolver(null, [], $this->container());
            $context = new RequestContext();
            $matcher = new UrlMatcher($routes, $context);

            $matcher->getContext()->fromRequest($request);

            $request->attributes->add($matcher->match($request->getPathInfo()));

            $controller = $controllerResolver->getController($request);

            $arguments = $argumentResolver->getArguments($request, $controller);

            if ($request->isMethod('POST')) {
                $this->logger->info("Pionia Request: ", ['method' => $request->getMethod(), 'path' => $request->getPathInfo(), 'data' => $request->getData()->all()]);
            } else {
                $this->logger->info("Pionia Request: ", ['method' => $request->getMethod(), 'path' => $request->getPathInfo()]);
            }

            $this->app->dispatch(new PreSwitchRunEvent($this, $request), PreSwitchRunEvent::name());

            // we inject the application into the request so that we can access it in the switch
            $request->setApplication($this->app);
            // forward the request to the switch
            $response = call_user_func_array($controller, $arguments);

        } catch (Exception | Throwable $e) {
            $this->logger->error("Error handling request " . $request->getMethod() . "::" . $request->getUri(), ["error" => $e->getMessage()]);
            $response = BaseResponse::jsonResponse(500, $e->getMessage());
        }

        return $this->terminate($request, $response);
    }

    /**
     * This method is called after the request has been handled
     * @param Request $request
     * @param BaseResponse $response
     * @return Response
     */
    public function terminate(Request $request, BaseResponse $response): Response
    {
        $this->app->dispatch(new PostSwitchRunEvent($this, $request, $response->data->all()), PostSwitchRunEvent::name());
        $res = new Response($response->getPrettyResponse(), ResponseAlias::HTTP_OK, ['content-type' => 'application/json']);
        $this->app->getSilently(PioniaCors::class)?->register()?->resolveRequest($request, $res);
        $this->app->getSilently(MiddlewareChain::class)?->handle($request, $res);
        return $res->prepare($request)->send();
    }


    public function container(): Container
    {
        return $this->app->context;
    }

    /**
     * Boot the kernel. This also runs the middleware chain and the authentication chain
     * @param Request $request
     */
    public function boot(Request $request): void
    {
        app->dispatch(new PreKernelBootEvent($this, $request), PreKernelBootEvent::name());
        app->getSilently(PioniaCors::class)?->register()?->resolveRequest($request);
        // run the middleware chain
        $middlewareChain = $this->app->getSilently(MiddlewareChain::class);
        if ($middlewareChain) {
            $middlewareChain->handle($request);
        }

        // run the authentication chain
        $authMiddleware = $this->app->getSilently(AuthenticationChain::class);

        if ($authMiddleware) {
            $authMiddleware->handle($request);
        }

    }

    public function getApplication(): PioniaApplication
    {
        return $this->app;
    }
}
