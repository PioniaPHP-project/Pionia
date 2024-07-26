<?php

namespace Pionia\Core\Config;

use Exception;
use JetBrains\PhpStorm\NoReturn;
use Pionia\Core\Helpers\SupportedHttpMethods;
use Pionia\Core\Helpers\Utilities;
use Pionia\Core\Pionia;
use Pionia\Core\Routing\BaseRoutes;
use Pionia\Logging\PioniaLogger;
use Pionia\Request\Request;
use Pionia\Response\BaseResponse;
use Pionia\Response\Response;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;


if (!defined('logger')){
    define('logger', PioniaLogger::init());
}

if (!defined("pionia")){
    define('pionia', Pionia::boot());
}

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
 * @property $routes BaseRoutes - Instance of the base routes
 * @property $context RequestContext - Instance of the request context
 * @property $matcher UrlMatcher - Instance of the url matcher
 * @property $middleware array - Array of middleware classes
 * @property $authBackends array - Array of authentication backends
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class CoreKernel
{
    private ?RequestContext $context = null;
    private ?UrlMatcher $matcher = null;


    private array $middleware = [];

    private array $authBackends = [];

    public function resolveCors(): void
    {
        $settings = pionia::getSettingOrDefault('cors', []);
        $allowedOrigins = $settings['ALLOW_ORIGIN'] ?? '*';
        $allowedHeaders = $settings['ALLOW_HEADERS'] ?? '*';
        $allowedCredentials = $settings['ALLOW_CREDENTIALS'] ?? 'false';
        $maxAge = $settings['MAX_AGE'] ?? 3600;
        header('Access-Control-Allow-Origin: '.$allowedOrigins);
        header('Access-Control-Allow-Headers: '.$allowedHeaders);
        header('Access-Control-Allow-Methods: '.SupportedHttpMethods::POST.', '.SupportedHttpMethods::GET.', OPTIONS');
        header('Access-Control-Allow-Credentials: '.$allowedCredentials);
        header('Access-Control-Max-Age: '.$maxAge);
    }

    public function __construct(
        private readonly BaseRoutes $routes,
    ){
    }

    /**
     * @throws Exception
     */
    private function mergeMiddlewaresFromSettings(): void
    {
        $otherMiddlewares = pionia::getSetting('middlewares');
        if ($otherMiddlewares){
            $middlewares = array_values($otherMiddlewares);
            foreach ($middlewares as $middleware){
                $check = Utilities::extends($middleware, 'Pionia\Core\Interceptions\BaseMiddleware');
                if ($check === 'NO_CLASS'){
                    throw new Exception("Middleware $middleware was not found, are you sure you created it?");
                } elseif ($check === 'DOES_NOT'){
                    throw new Exception("Middleware $middleware must extend Pionia\Core\Interceptions\BaseMiddleware");
                }

                $this->middleware[] = $middleware;
            }
        }
    }

    /**
     * @throws Exception
     */
    private function mergeAuthenticationsFromSettings(): void
    {
        $otherAuths = pionia::getSetting('authentications');
        if ($otherAuths){
            $auths = array_values($otherAuths);
            foreach ($auths as $auth){
                $check = Utilities::extends($auth, 'Pionia\Core\Interceptions\BaseAuthenticationBackend');
                if ($check === 'NO_CLASS'){
                    throw new Exception("Authentication backend $auth was not found, are you sure you created it?");
                } elseif ($check === 'DOES_NOT'){
                    throw new Exception("Authentication backend $auth must extend Pionia\Core\Interceptions\BaseAuthenticationBackend");
                }

                $this->authBackends[] = $auth;
            }
        }
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

        $serverSettings = pionia::getSetting('server');

        $shouldLog = isset($serverSettings['LOG_REQUESTS']) && $serverSettings['LOG_REQUESTS'];

        $shouldLogResponse = $serverSettings['LOG_RESPONSES'] ?? $shouldLog;

        try {
            $request->attributes->add($this->matcher->match($request->getPathInfo()));

            $controller = $controllerResolver->getController($request);

            $arguments = $argumentResolver->getArguments($request, $controller);

            if ($shouldLog && $request->isMethod('POST')) {
                logger->info("Pionia Request: ", PioniaLogger::hideInLogs($request->getData()));
            } else {
                logger->info("Pionia Request: ", ['method' => $request->getMethod(), 'path' => $request->getPathInfo()]);
            }

            $response =  call_user_func_array($controller, $arguments);

            if ($shouldLogResponse) {
                logger->info('Pionia Response: ', ['response' => $response->getPrettyResponse()]);
            }

            $requestResponse = new Response($response->getPrettyResponse(), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        } catch (ResourceNotFoundException $exception) {
            if ($shouldLog){
                logger->debug($exception->getMessage(), ['stack' => $exception->getTraceAsString()]);
            }
            $response = BaseResponse::JsonResponse(404, 'Resource not found, are you sure this endpoint exists?');
            $requestResponse = new Response($response->getPrettyResponse(), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        } catch (Exception $exception) {
            if ($shouldLog){
                logger->debug($exception->getMessage(), ['stack' => $exception->getTraceAsString()]);
            }
            $response = BaseResponse::JsonResponse(500, $exception->getMessage());
            $requestResponse =  new Response($response->getPrettyResponse(), Response::HTTP_OK, ['Content-Type' => 'application/json']);
        }

        return $requestResponse;
    }

    /**
     * Bootstraps the entire application
     * It automatically resolves the request internally, middlewares, authentication backends and the controller
     * @return Response
     * @since 1.1.1 - This method was added to replace the handle method. The handle method will be removed in future versions
     */
    public function run(): Response
    {
        $request = Request::createFromGlobals();

        $this->resolveFrontEnd($request);

        try {
            $request = $this->resolveMiddlewares($request); // first run for all middlewares
            $request =$this->resolveAuthenticationBackend($request); // run all the authentication middles
            $response = $this->resolve($request);
            $request = $this->resolveMiddlewares($request, $response);
            return $response->prepare($request)->send();
        } catch (Exception $exception) {
            $response = BaseResponse::JsonResponse(500, $exception->getMessage());
            $reqRes =  new Response($response->getPrettyResponse(), ResponseAlias::HTTP_OK, ['Content-Type' => 'application/json']);
            return $reqRes->prepare($request)->send();
        }
    }

    /**
     * Runs every registered middleware pre and post controller execution. Exposing both the request and response the middleware
     * @param Request $request
     * @param Response|null $response
     * @return Request
     * @throws Exception
     */
    private function resolveMiddlewares(Request $request, Response | null $response = null): Request
    {
        $this->mergeMiddlewaresFromSettings();
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

    /**
     * Resolves all authentication middlewares using the `authenticationBackendWorker` method
     * @throws Exception
     */
    private function resolveAuthenticationBackend(Request $request): Request
    {
        $this->mergeAuthenticationsFromSettings();
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
     * @return Request
     */
    private function authenticationBackendWorker(Request $request, array $backends ): Request
    {
        if ($request->isAuthenticated() || ($request->getAuth() && $request->getAuth()->user)) {
            return $request;
        }

        $current = array_shift($backends);

        $klass = new $current();

        $userObject =  $klass->authenticate($request);

        // if there is an instance, we set it to context and the next  iteration will be terminated immediately
        if ($userObject){
            $request->setAuthenticationContext($userObject);
            return $request;
        }

        // if we still have more, we call the next
        if (count($backends) > 0) {
            return $this->authenticationBackendWorker($request, $backends);
        }

        // otherwise we abort the process and return the request and proceed. It will be handled on the action or service
        // level
        return $request;
    }

    /**
     * Resolves the front end if the request is a get request and the path is not an api path
     *
     * Using any frontend of your choice, you can serve the front end from the root of the project
     * @param Request $request
     * @return void
     */
    private function resolveFrontEnd(Request $request): void
    {
        if (!str_starts_with($request->getPathInfo(), '/api') && strtolower($request->getMethod()) === 'get'){
            $fileSystem = new Filesystem();
            if ($fileSystem->exists(BASEPATH . '/index.html')){
                $this->serveSpa();
            }
            $response = new Response(file_get_contents(__DIR__ . '/index.php'), Response::HTTP_OK, ['Content-Type' => 'text/html']);
            $response->prepare($request)->send();
            exit();
        }
    }

    /**
     * Serves Single Page Applications. This is useful for serving the front end from the root of the project.
     * Handles also cases where users refreshes the url from relative paths to the frontend
     * @return void
     */
    #[NoReturn] private function serveSpa(): void
    {
        $base = BASEPATH;
        if (!str_ends_with($base, '/')) {
            $base = $base . '/';
        }
        $path = $base . 'index.html';
        $response = new Response(file_get_contents($path), ResponseAlias::HTTP_OK, ['Content-Type' => 'text/html']);
        $response->prepare(Request::createFromGlobals())->send();
        exit();
    }
}
