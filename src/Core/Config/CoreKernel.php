<?php

namespace Pionia\Core\Config;

use Exception;
use Pionia\Core\Helpers\SupportedHttpMethods;
use Pionia\Core\Helpers\Utilities;
use Pionia\Core\Pionia;
use Pionia\Core\Routing\BaseRoutes;
use Pionia\Logging\PioniaLogger;
use Pionia\Request\Request;
use Pionia\Response\BaseResponse;
use Pionia\Response\Response;
use Symfony\Component\Filesystem\Filesystem;
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
        $allowedCredentials = $settings['ALLOW_CREDENTIALS'] ?? 'true';
        $maxAge = $settings['MAX_AGE'] ?? 3600;
        header('Access-Control-Allow-Origin: '.$allowedOrigins);
        header('Access-Control-Allow-Headers: '.$allowedHeaders);
        header('Access-Control-Allow-Methods: '.SupportedHttpMethods::POST.', '.SupportedHttpMethods::GET.', OPTIONS');
        header('Access-Control-Allow-Credentials: '.$allowedCredentials);
        header('Access-Control-Max-Age: '.$maxAge);
    }

    public function __construct(
        private BaseRoutes $routes,
    ){
    }

    /**
     * @param array|string $middleware
     * @return CoreKernel
     *
     * @deprecated - register middlewares directly in the settings.ini file. Will be removed from future versions
     */
    public function registerMiddleware(array | string $middleware): static
    {
        if (is_array($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        }else{
            $this->middleware[] = $middleware;
        }
        return $this;
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
     * @param array|string $auth_backends
     * @return CoreKernel
     *
     * @deprecated - register authentications directly in the settings.ini file. Will be removed from future versions
     */
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
     * This is the main method that runs the entire request cycle.
     * @param Request $request
     * @return Response
     *
     * @deprecated - This method will be removed in future versions. Use the run method instead
     */
    public function handle(Request $request): Response
    {
        $this->resolveFrontEnd($request);

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
     * This is the main method that runs the entire request cycle.
     * It automatically resolves the request internally.
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
            $reqRes =  new Response($response->getPrettyResponse(), Response::HTTP_OK, ['Content-Type' => 'application/json']);
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
        return $request;
    }

    private function resolveFrontEnd(Request $request): void
    {
        if (!str_starts_with($request->getPathInfo(), '/api') && strtolower($request->getMethod()) === 'get'){
            $fileSystem = new Filesystem();
            $base = BASEPATH;
            if (!str_ends_with($base, '/')){
                $base = $base . '/';
            }
            $path = $base . 'public/index.html';

            if ($fileSystem->exists($path)){
                $response = new Response(file_get_contents($path), Response::HTTP_OK, ['Content-Type' => 'text/html']);
            } else {
                $response = new Response(file_get_contents(__DIR__ . '/index.php'), Response::HTTP_OK, ['Content-Type' => 'text/html']);
            }
            $response->send();
            exit();
        }
    }

    public function detectContentType(string $extension): string
    {
        $mimeTypes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf',
        ];
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

}
