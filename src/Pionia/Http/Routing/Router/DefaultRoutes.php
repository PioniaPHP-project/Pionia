<?php

namespace Pionia\Http\Routing\Router;

use DIRECTORIES;
use Pionia\Http\Pages\FrameworkWelcomePage;
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
            ->addRouteForMediaFiles()
            ->addFrameworkAssetsRoute();

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
        $userIndex = path(directoryFor(DIRECTORIES::PUBLIC_DIR->name) . DIRECTORY_SEPARATOR . 'index.html');

        if (is_file($userIndex)) {
            return new Response(
                (string) file_get_contents($userIndex),
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        return FrameworkWelcomePage::for($request, realm())->toResponse();
    }

    private function addFrameworkAssetsRoute(): static
    {
        $this->defaultRoutes->add(
            'pionia_assets',
            RouteObject::get('/__pionia/{path}')
                ->options(['path' => '.+'])
                ->controller(['_controller' => DefaultRoutes::class . '::frameworkAssetsRouter'])
                ->build()
        );

        return $this;
    }

    public function frameworkAssetsRouter(Request $request): Response | BinaryFileResponse
    {
        $_path = $request->attributes->get('path');
        $requestedFile = $this->resolvePathWithinBase(FrameworkWelcomePage::resourcesPath(), $_path);

        if ($requestedFile === null) {
            return $this->errorMessage($request, 404, 'Framework asset not found');
        }

        $mime = $this->guessMimeType($requestedFile);
        $response = new BinaryFileResponse($requestedFile);
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($requestedFile));

        return $response;
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
        $defaultMimeMap = [
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'ico' => 'image/x-icon',
            'svg' => 'image/svg+xml',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'html' => 'text/html',
            'txt' => 'text/plain',
        ];

        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION) ?: '');

        try {
            $mimeTypes = new MimeTypes();
            $guessed = $mimeTypes->guessMimeType($file);
            if ($guessed !== null) {
                return $guessed;
            }
        } catch (\Throwable) {
        }

        return $defaultMimeMap[$extension] ?? 'application/octet-stream';
    }

    private function resolvePathWithinBase(string $baseDir, string $relativePath): ?string
    {
        $base = str_starts_with($baseDir, DIRECTORY_SEPARATOR)
            ? $baseDir
            : path($baseDir);
        $baseReal = realpath($base);
        if ($baseReal === false) {
            return null;
        }

        $candidate = $baseReal . DIRECTORY_SEPARATOR . ltrim($relativePath, '/');
        $resolved = realpath($candidate);
        if ($resolved === false || !is_file($resolved)) {
            return null;
        }

        $basePrefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($resolved, $basePrefix) && $resolved !== $baseReal) {
            return null;
        }

        return $resolved;
    }

    /**
     * Serve any other files in the static folder
     * @return Response|BinaryFileResponse
     */
    public function staticFilesRouter(Request $request): Response | BinaryFileResponse
    {
        $_path = $request->attributes->get('path');
        $staticBase = alias(DIRECTORIES::STATIC_DIR->name);
        $requestedFile = $this->resolvePathWithinBase($staticBase, $_path);

        if ($requestedFile === null) {
            return $this->errorMessage($request, 404, 'Resource not found or did not match any endpoints');
        }

        $mime = $this->guessMimeType($requestedFile);

        $response = new BinaryFileResponse($requestedFile);
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($requestedFile));

        return $response;
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
        $path = $request->attributes->get('path');
        $uploadSettings = env('uploads', ['media_dir' => 'media']);
        $mediaDir = $uploadSettings['media_dir'] ?? 'media';
        $mediaBase = alias(DIRECTORIES::STORAGE_DIR->name) . DIRECTORY_SEPARATOR . $mediaDir;
        $requestedFile = $this->resolvePathWithinBase($mediaBase, $path);

        if ($requestedFile === null) {
            return $this->errorMessage($request, 404, 'Resource not found or did not match any endpoints');
        }

        $mime = $this->guessMimeType($requestedFile);

        $response = new BinaryFileResponse($requestedFile);
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($requestedFile));

        return $response;
    }

}
