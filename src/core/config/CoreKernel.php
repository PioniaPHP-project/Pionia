<?php

namespace Pionia\core\config;

use Exception;
use Monolog\Logger;
use Pionia\core\Pionia;
use Pionia\core\routing\BaseRoutes;
use Pionia\Logging\PioniaLogger;
use Pionia\request\Request;
use Pionia\response\BaseResponse;
use Pionia\response\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

/**
 * This is the core kernel that handles all the request processing and middleware execution
 *
 * It is executes the entire request cycle.
 *
 * The request cycle is as follows:
 *
 *     1. Resolve all middlewares against the request only
 *     2. Resolve all authentication backends till we have a user or we run out of backends
 *     3. Resolve the request and enter the controller
 *     4. Resolve all middlewares again but this time against the request and response since we have both
 *     5. Return the response to the client
 *
 * It also catches all exceptions and returns a 200 ok response with error code 500
 *
 * @property $name string - The name of the application
 * @property $version string - The version of the application
 * @property $routes BaseRoutes - Instance of the base routes
 * @property $context RequestContext - Instance of the request context
 * @property $matcher UrlMatcher - Instance of the url matcher
 * @property $middleware array - Array of middleware classes
 * @property $authBackends array - Array of authentication backends
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CoreKernel extends Pionia
{
    private ?RequestContext $context = null;
    private ?UrlMatcher $matcher = null;

    private ?Logger $logger = null;

    private $middleware = [];

    private $authBackends = [];

    public function __construct(
        private BaseRoutes $routes,
    ){
        parent::__construct();
        $this::resolveSettingsFromIni();

        $this->logger = PioniaLogger::init();
    }

    public function registerMiddleware(array | string $middleware): static
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        }else{
            $this->middleware[] = $middleware;
        }
        return $this;
    }

    public function registerAuthBackends(array | string $auth_backends): static
    {
        if (is_string($auth_backends)){
            $this->authBackends[] = $auth_backends;
        } else {
            $this->authBackends = array_merge($this->authBackends, $auth_backends);
        }
        return $this;
    }


    /**
     * @throws Exception
     */
    private function resolve(Request $request): Response
    {
        $controllerResolver = new ControllerResolver();
        $argumentResolver = new ArgumentResolver();

        // prepare the request context
        if(!$this->context || !is_a($this->context, 'Symfony\Component\Routing\RequestContext')){
            $this->context = new RequestContext();
        }
        // prepare the routes
        if (!$this->matcher || !is_a($this->matcher, 'Symfony\Component\Routing\Matcher\UrlMatcher')) {
            $this->matcher = new UrlMatcher($this->routes, $this->context);
        }

        $this->matcher->getContext()->fromRequest($request);

        try {
            $request->attributes->add($this->matcher->match($request->getPathInfo()));

            $controller = $controllerResolver->getController($request);

            $arguments = $argumentResolver->getArguments($request, $controller);

            $response =  call_user_func_array($controller, $arguments);

            $requestResponse = new Response($response->getPrettyResponse(), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        } catch (ResourceNotFoundException $exception) {
            $response = BaseResponse::JsonResponse(404, 'Resource not found, are you sure this endpoint exists?');
            $requestResponse = new Response($response->getPrettyResponse(), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        } catch (Exception $exception) {
            $response = BaseResponse::JsonResponse(500, $exception->getMessage());
            $requestResponse =  new Response($response->getPrettyResponse(), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        return $requestResponse;
    }

    public function handle(Request $request): Response
    {
        try {
            $request = $this->resolveMiddlewares($request); // first run for all middlewares
            $request =$this->resolveAuthenticationBackend($request); // run all the authentication middles

            $response = $this->resolve($request);
            $request = $this->resolveMiddlewares($request, $response);
            return $response->prepare($request)->send();
        } catch (Exception $exception) {
            $response = BaseResponse::JsonResponse(500, $exception->getMessage());
            $reqRes =  new Response($response->getPrettyResponse(), Response::HTTP_OK, ['Content-Type' => 'application/json']);
            return $reqRes->prepare($request)->send();
        }
    }

    /**
     * Runs every registered middleware pre and post controller execution. Exposing both the request and response the middleware
     * @param Request $request
     * @param Response|null $response
     * @return Request
     */
    public function resolveMiddlewares(Request $request, Response | null $response = null): Request
    {
        // we want to run them if we have them
        if (count($this->middleware) > 0) {
            foreach ($this->middleware as $middleware) {
                // call the class
                $klass = new $middleware();
                $klass->run($request, $response);
            }
        }

        return $request;
    }

    public function resolveAuthenticationBackend(Request $request): Request
    {
        if (count($this->authBackends) > 0) {
            // we take a snapshot
            $backends = $this->authBackends;
            return $this->authenticationBackendWorker($request, $backends);
        }
        return $request;
    }

    /**
     * This will run until any of the backends successfully authenticates the user
     *
     * or until all the backends are complete
     * @param Request $request
     * @param array $backends
     * @return $this
     */
    private function authenticationBackendWorker(Request $request, array $backends ): Request
    {
        if ($request->isAuthenticated() || $request->getAuth()->user) {
            return $request;
        }

        $current = array_shift($backends);

        $klass = new $current();
        $userObject =  $klass->authenticate($request);

        // if the instance, we set it to context and the next next iteration will be terminated immediately
        if ($userObject){
            $request->setAuthenticationContext($userObject);
        }

        // if we still have more, we call the next
        if (count($backends) > 0) {
            return $this->authenticationBackendWorker($request, $backends);
        }
        return $request;
    }

}
