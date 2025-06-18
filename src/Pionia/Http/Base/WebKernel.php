<?php

namespace Pionia\Http\Base;

use Pionia\Auth\AuthenticationChain;
use Pionia\Contracts\KernelContract;
use Pionia\Http\Base\Events\PreKernelBootEvent;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Middlewares\MiddlewareChain;
use Pionia\Realm\AppRealm;
use Pionia\Realm\RealmContract;
use Pionia\Utils\Microable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Throwable;

class WebKernel implements KernelContract
{
    use Microable;

    private function prepareRequest(Request $request): Response | BinaryFileResponse
    {
        $context = new RequestContext();
        $request = $this->boot($request);
        $context->fromRequest($request);
        $routes = app()->getSilently(AppRealm::APP_ROUTES_TAG);
        $matcher = new UrlMatcher($routes, $context);
        $matched = $matcher->match($request->getPathInfo());
        $request->attributes->add($matched);
        $controllerResolver = new ControllerResolver(logger());
        $argumentResolver = new ArgumentResolver(null, [], container());
        $controller = $controllerResolver->getController($request);
        $arguments = $argumentResolver->getArguments($request, $controller);
        return call_user_func_array($controller, $arguments);
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->prepareRequest($request);
        } catch (Throwable $e) {
            logger()->error("Error handling request " . $request->getMethod() . "::" . $request->getUri(), ["error" => $e->getMessage()]);
            $response =  (new Response(response(env('SERVER_ERROR_CODE', 500), $e->getMessage())->getPrettyResponse(), 200));
        }
        return $this->terminate($response, $request);
    }

//    public function handles(Request $request): Response
//    {
//        try {
//            realm()->make(PioniaCors::class)->handle($request);
//            $request = $this->boot($request);
//            $routes = realm()->getSilently(AppRealm::APP_ROUTES_TAG);
//            // prepare the request for symfony routing
//            $controllerResolver = new ControllerResolver(logger());
//            $argumentResolver = new ArgumentResolver(null, [], $this->container());
//            $context = new RequestContext();
//            $matcher = new UrlMatcher($routes, $context);
//            $matcher->getContext()->fromRequest($request);
//            $parameters = $matcher->match($request->getPathInfo());
//            dd($parameters);
//            $request->attributes->add($parameters);
//            dd($parameters);
//
//            dd($request);
//
//
//            $controller = $controllerResolver->getController($request);
//            $arguments = $argumentResolver->getArguments($request, $controller);
//            if ($request->isMethod('POST')) {
//                logger()->info("Pionia Request: ", ['method' => $request->getMethod(), 'path' => $request->getPathInfo(), 'data' => $request->getData()->all()]);
//            } else {
//                logger()->info("Pionia Request: ", ['method' => $request->getMethod(), 'path' => $request->getPathInfo()]);
//            }
//            realm()->event()->dispatch(new PreSwitchRunEvent($this, $request), PreSwitchRunEvent::name());
//            // we inject the application into the request so that we can access it in the switch
//            $request->setApplication(app());
//            // forward the request to the switch
//            $response = call_user_func_array($controller, $arguments);
//
//        } catch (Exception | Throwable $e) {
//            logger()->error("Error handling request " . $request->getMethod() . "::" . $request->getUri(), ["error" => $e->getMessage()]);
//            $response = response(returnCode: env('SERVER_ERROR_CODE', 500), returnMessage: $e->getMessage());
//        }
//
//        return $this->terminate($request, $response);
//    }




    /**
     * This method is called after the request has been handled
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function terminate(Response $response, Request $request):Response
    {
        $middlewareChain = realm()->getSilently(MiddlewareChain::class);
        if ($middlewareChain) {
            $middlewareChain->handle($request, $response);
        }
        return $response->prepare($request)->send();
    }


    /**
     * Boot the kernel. This also runs the middleware chain and the authentication chain
     * @param Request $request
     * @return Request
     */
    public function boot(Request $request): Request
    {
        event(new PreKernelBootEvent($this, $request), PreKernelBootEvent::name());
        // run the middleware chain
        $middlewareChain = realm()->getSilently(MiddlewareChain::class);
        if ($middlewareChain) {
            $middlewareChain->handle($request);
        }

        // run the authentication chain
        $authMiddleware = realm()->getSilently(AuthenticationChain::class);

        if ($authMiddleware) {
            $authMiddleware->handle($request);
        }

        return $request;

    }
}
