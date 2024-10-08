<?php

namespace Pionia\Http\Base;

use DI\Container;
use DIRECTORIES;
use Exception;
use JetBrains\PhpStorm\NoReturn;
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
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\Controller\ControllerResolver;
use Symfony\Component\Mime\MimeTypes;
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

    private function isApiRequest(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/');
    }

    /**
     * if the request is on root
     * E.g. http://localhost:8080/ -- here we either load the default landing framework page or load any
     * .html file we find in the statics folders
     * @param Request $request
     * @return bool
     */
    private function isRoot(Request $request): bool
    {
        return $request->getPathInfo() === '' || $request->getPathInfo() === '/';
    }

    /**
     * If its an upload we load it from the media folder in our storage
     * @param Request $request
     * @return bool
     */
    private function isMedia(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/media/');
    }

    public function handle(Request $request): Response
    {
        try {
            if ($request->isMethod('GET') && !$this->isApiRequest($request)){
                $this->resolveFrontEnd($request);
            }
            $request = $this->boot($request);
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
            $response = response(returnCode: env('SERVER_ERROR_CODE', 500), returnMessage: $e->getMessage());
        }

        return $this->terminate($request, $response);
    }

    /**
     * Resolves the front end if the request is a get request and the path is not an api path
     *
     * Using any frontend of your choice, you can serve the front end from the root of the project
     * @param Request $request
     * @return void
     */
    #[NoReturn]
    private function resolveFrontEnd(Request $request): void
    {
        $fs = new Filesystem();
        if ($this->isMedia($request)){
            $path = trim($request->getPathInfo(), '/');
            $filePath = alias(DIRECTORIES::STORAGE_DIR->name).DIRECTORY_SEPARATOR.$path;
            if ($fs->exists($filePath)) {
                $file = new File($filePath);
                $response = new Response($file->getContent(), ResponseAlias::HTTP_OK, ['Content-Type' => $file->getMimeType()]);
                $this->app->getSilently(PioniaCors::class)?->register()?->resolveRequest($request, $response);
                $response->prepare($request)->send(false);
            } else {
                $response = new Response(response(
                    returnCode: env('NOT_FOUND_CODE', 404),
                    returnMessage: "File not found"
                )->getPrettyResponse(), ResponseAlias::HTTP_OK);
                $response->prepare($request)->send();
            }
            exit();
        } else if ($this->isRoot($request)) {
            $this->serveSpa($request, $fs);
        }  else {
            // check if its file(images, js, css, etc) and load it
            $path = trim($request->getPathInfo(), '/');
            $filePath = asset($path);
            if ($fs->exists($filePath)) {
                $file = new File($filePath);
                $response = new Response($file->getContent(), ResponseAlias::HTTP_OK, ['Content-Type' => $file->getMimeType()]);
                $this->app->getSilently(PioniaCors::class)?->register()?->resolveRequest($request, $response);
                $response->prepare($request)->send(false);
                exit();
            } else {
                $this->loadWelcomePage($request);
            }
        }
    }

    public function autoDiscoverContentType(string $filePath): string
    {
        $mimes = new MimeTypes();
        return $mimes->guessMimeType($filePath);
    }

    /**
     * The developer can override and access the entire context of the application in the overridden page
     * @param Request $request
     * @return void
     */
    #[NoReturn] private function loadWelcomePage(Request $request): void
    {
        $override = alias(DIRECTORIES::STATIC_DIR->name).DIRECTORY_SEPARATOR.'index.php';
        if (file_exists($override)) {
            render($override, [
                'app' => $this->app,
                'request' => $request,
                'container' => $this->container()
            ]);
        } else {
            $welcomePage = alias(DIRECTORIES::WELCOME_PAGE->name);
            render($welcomePage, [
                'app' => $this->app,
                'request' => $request,
                'container' => $this->container(),
            ]);
        }
    }

    /**
     * Serve the landing html file of the spa if it exists
     * @param Request $request
     * @param Filesystem $fs
     * @return void
     */
    #[NoReturn]
    private function serveSpa(Request $request, Filesystem $fs): void
    {
        $path = alias(DIRECTORIES::STATIC_DIR->name).DIRECTORY_SEPARATOR.'index.html';
        if (!$fs->exists($path)) {
            $this->loadWelcomePage($request);
        } else {
            $file = new File($path);
            $response = new Response($file->getContent(), ResponseAlias::HTTP_OK, ['Content-Type' => $file->getMimeType()]);
            $response->prepare($request)->send();
            exit();
        }
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
     * @return Request
     */
    public function boot(Request $request): Request
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

        return $request;

    }

    public function getApplication(): PioniaApplication
    {
        return $this->app;
    }
}
