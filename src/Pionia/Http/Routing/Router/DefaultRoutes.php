<?php

namespace Pionia\Http\Routing\Router;

use DIRECTORIES;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Realm\AppRealm;
use Pionia\Realm\RealmContract;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\RouteCollection;

class DefaultRoutes
{
    private RouteCollection  $defaultRoutes;
    public function __construct()
    {
        $this->defaultRoutes = new RouteCollection();
    }

    private function errorMessage($request, $code, $message): Response
    {
        $json = $request->query->has('json');
        if ($json){
            return new Response(response($code, $message)->getPrettyResponse(), 200, ['application/json']);
        } else {
            $html = "<div style='
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    background: #f5f7fa;
                    font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;
                '>
                    <div style='
                        background: white;
                        padding: 40px 60px;
                        border-radius: 16px;
                        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
                        text-align: center;
                        max-width: 500px;
                    ' role='alert'>
                        <img src='/static/favicon.ico' alt='App Icon' style='width: 90px; margin-bottom: 20px;'>
                        <div style='font-size: 82px; font-weight: 600; color: #2b2b2b;'>{$code}</div>
                        <div style='font-size: 20px; color: #666;'>{$message}</div>
                    </div>
                </div>";
            return new Response($html, $code, ['text/html']);
        }
    }

    /**
     * Unless the developer re-defines the folder for serving static html, css and js, we shall assume that it
     * is served at /static/
     * @return DefaultRoutes
     */
    private function addRouteForHome(): static
    {
        $this->defaultRoutes->add(
            'home', RouteObject::get('/')
            ->controller(['_controller' => DefaultRoutes::class . '::homeResolver'])
            ->build()
        );
        return $this;
    }

    /**
     * Adds the default routes for the homepage, statics files and html
     * @return $this
     */
    public function collect(RealmContract $appRealm): static
    {
       $this->addRouteForHome()
            ->addRouteForMediaFiles();

        $routes = $appRealm->getOrDefault(AppRealm::APP_ROUTES_TAG, new RouteCollection());
        $routes->addCollection($this->defaultRoutes);
        $appRealm->updateCache(AppRealm::APP_ROUTES_TAG, $routes, true, 10);
        $appRealm->set(AppRealm::APP_ROUTES_TAG, $routes);
       return $this;
    }

    public static function collectStaticRoutes(RealmContract $appRealm): static
    {
        $instance = new static();
        $instance
//            ->otherStaticFilesRouter()
            ->addStaticFiles();
        $routes = $appRealm->getOrDefault(AppRealm::APP_ROUTES_TAG, new RouteCollection());
        $routes->addCollection($instance->defaultRoutes);
        $appRealm->updateCache(AppRealm::APP_ROUTES_TAG, $routes, true, 10);
        $appRealm->set(AppRealm::APP_ROUTES_TAG, $routes);
        return $instance;
    }

    public function homeResolver(Request $request): Response
    {
        // check if we have an index.html in the static folder otherwise, serve the inbuilt html
        $fileManager = new Filesystem();
        $staticPage = path(directoryFor(DIRECTORIES::STATIC_DIR->name).DIRECTORY_SEPARATOR.'index.html');
        $response = new Response();

        if ($fileManager->exists($staticPage)) {
            // send the file here
            $content = $fileManager->readFile($staticPage);
            $response->setContent($content);
        } else {
            $welcomePage = __DIR__.'/../../../templates/index.php';
            render($welcomePage, [
                    'app' => realm(),
                    'request' => $request,
                ]);
        }
        $response =  new Response($content, 200, ['Content-Type' => 'text/html']);
        return $response->prepare($request)->send();
    }

    /**
     * Resolves all files served in the static folder
     * @return $this
     */
    public function otherStaticFilesRouter(): static
    {
        $this->defaultRoutes->add(
            'statics', RouteObject::get('/{path}')
            ->requires(['path' => '.+'])
            ->controller(['_controller' => DefaultRoutes::class . '::staticFilesRouter'])
            ->build()
        );
        return $this;
    }

    private function guessMimeType($file)
    {
        $extension = pathinfo($file, PATHINFO_EXTENSION);
        $defaultMimeMap = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'html' => 'text/html',
        ];
        $mimeTypes = new MimeTypes();
       return $mimeTypes->guessMimeType($file)
            ?? $defaultMimeMap[strtolower($extension)]
            ?? 'application/octet-stream';
    }

    /**
     * Serve any other files in the static folder
     * @return Response|BinaryFileResponse
     */
    public function staticFilesRouter(Request $request): Response | BinaryFileResponse
    {
        $fileManager = new Filesystem();
        $_path = $request->attributes->get('path');
        $requestedFile = path(directoryFor(DIRECTORIES::STATIC_DIR->name).DIRECTORY_SEPARATOR.$_path);
        if (!$requestedFile || !$fileManager->exists($requestedFile)) {
            return $this->errorMessage($request, 404, 'Resource not found or did not match any endpoints')
                ->prepare($request)->send();
        }

        // Get MIME type
        $mime = $this->guessMimeType($requestedFile);


        // Serve a file
        $response = new BinaryFileResponse($requestedFile);
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($requestedFile));
        return $response->prepare($request)->send();
    }

    /**
     * For resolving static files in the static folder, shall resolves files in folders too
     * @return $this
     */
    private function addStaticFiles(): static
    {
        $this->defaultRoutes->add(
            'static', RouteObject::get('/static/{path}')
            ->options(['path' => '.+'])
            ->controller(['_controller' => DefaultRoutes::class . '::staticFilesRouter'])
            ->build()
        );
        return $this;
    }


    /**
     * For resolving media files from the media/storage directory
     * @return $this
     */
    private function addRouteForMediaFiles(): static
    {
        $this->defaultRoutes->add('media', RouteObject::get('/media/{path}')
            ->controller(['_controller' => DefaultRoutes::class . '::mediaFilesRouter'])
            ->options(['path' => '.+'])
            ->build());
        return $this;
    }

    public function mediaFilesRouter(Request $request): Response | BinaryFileResponse
    {
        // Get the dynamic {path} parameter from the route
        $path = $request->attributes->get('path');

        $requestedFile = path(directoryFor(DIRECTORIES::STATIC_DIR->name).DIRECTORY_SEPARATOR.$path);


        // Security check to prevent directory traversal
        if (
            !$requestedFile ||
            !str_starts_with($requestedFile, realpath($requestedFile)) ||
            !is_file($requestedFile)
        ) {
            return $this->errorMessage( $request, 404, 'Resource not found or did not match any endpoints')
                ->prepare($request)->send();
        }

        // Determine content type (MIME)
        $mimeType = new MimeTypes();
        $mime = $mimeType->guessMimeType($requestedFile) ?: 'application/octet-stream';
        logger()->info($mime);

        // Serve the file
        $response = new BinaryFileResponse($requestedFile);
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($requestedFile));

        return $response->prepare($request)->send();
    }

}
