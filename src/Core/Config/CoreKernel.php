<?php

namespace JetPhp\Core\Config;

use Exception;
use JetPhp\Core\Base;
use JetPhp\Core\Routing\BaseRoutes;
use JetPhp\Request\Request;
use JetPhp\Response\BaseResponse;
use JetPhp\Response\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;

/**
 * @property $routes BaseRoutes - Instance of the base routes
 */
class CoreKernel extends Base
{
    private ?RequestContext $context = null;
    private ?UrlMatcher $matcher = null;

    private $middleware = [];

    private $authBackends = [];

    public function __construct(
        private BaseRoutes $routes,
    ){
        parent::__construct();
        $this::resolveSettingsFromIni();
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
