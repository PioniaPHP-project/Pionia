<?php

namespace Pionia\Http\Routing\Router;

use DIRECTORIES;
use Pionia\Http\Pages\HttpErrorPage;
use Pionia\Http\Pages\DeveloperStatsPage;
use Pionia\Http\Pages\FrameworkWelcomePage;
use Pionia\Http\Monitoring\StatsGate;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\BinaryFileResponse;
use Pionia\Http\Response\Response;
use Pionia\Documentation\ApiDocsUiExporter;
use Pionia\Documentation\DocsGate;
use Pionia\Documentation\MoonlightDocCollector;
use Pionia\Documentation\OpenApiExporter;
use Pionia\Http\Mime\MimeType;
use Pionia\Http\Routing\RouteTable;
use Pionia\Realm\AppRealm;
use Pionia\Realm\RealmContract;
use Pionia\Utils\SafePath;

class DefaultRoutes
{
    private RouteTable $defaultRoutes;
    public function __construct()
    {
        $this->defaultRoutes = new RouteTable();
    }

    private function errorMessage($request, $code, $message): Response
    {
        return HttpErrorPage::respond($request, (int) $code, (string) $message);
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
            ->addDocsRoutes()
            ->addStatsRoutes()
            ->addRouteForMediaFiles()
            ->addFrameworkAssetsRoute();

        $routes = $appRealm->getOrDefault(AppRealm::APP_ROUTES_TAG, new RouteTable());
        $routes->addCollection($this->defaultRoutes);
        $appRealm->updateCache(AppRealm::APP_ROUTES_TAG, $routes, true, 10);
        $appRealm->set(AppRealm::APP_ROUTES_TAG, $routes);
       return $this;
    }

    public static function collectStaticRoutes(RealmContract $appRealm): static
    {
        $instance = new static();
        $instance
            ->addPublicAssetRoutes()
            ->addSpaFallbackRoute()
            ->addStaticFiles();
        $routes = $appRealm->getOrDefault(AppRealm::APP_ROUTES_TAG, new RouteTable());
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

    private function addDocsRoutes(): static
    {
        $this->defaultRoutes->add(
            'docs',
            RouteObject::get('/docs')
                ->controller(['_controller' => DefaultRoutes::class . '::docsUiResolver'])
                ->build()
        );

        $this->defaultRoutes->add(
            'docs_openapi',
            RouteObject::get('/docs/openapi.json')
                ->controller(['_controller' => DefaultRoutes::class . '::openApiSpecResolver'])
                ->build()
        );

        return $this;
    }

    public function docsUiResolver(Request $request): Response
    {
        if ($denied = DocsGate::deny($request, htmlOnForbidden: true)) {
            return $denied;
        }

        $catalog = (new MoonlightDocCollector())->collect();
        $html = (new ApiDocsUiExporter())->render($catalog, DocsGate::specUrl($request));

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function openApiSpecResolver(Request $request): Response
    {
        if ($denied = DocsGate::deny($request)) {
            return $denied;
        }

        $catalog = (new MoonlightDocCollector())->collect();
        $json = (new OpenApiExporter())->export($catalog);

        return Response::json($json);
    }

    private function addStatsRoutes(): static
    {
        $this->defaultRoutes->add(
            'stats',
            RouteObject::get('/stats')
                ->controller(['_controller' => DefaultRoutes::class . '::statsUiResolver'])
                ->build()
        );

        $this->defaultRoutes->add(
            'stats_json',
            RouteObject::get('/stats.json')
                ->controller(['_controller' => DefaultRoutes::class . '::statsJsonResolver'])
                ->build()
        );

        return $this;
    }

    public function statsUiResolver(Request $request): Response
    {
        if ($denied = StatsGate::deny($request, htmlOnForbidden: true)) {
            return $denied;
        }

        return DeveloperStatsPage::for($request, realm())->toResponse();
    }

    public function statsJsonResolver(Request $request): Response
    {
        if ($denied = StatsGate::deny($request)) {
            return $denied;
        }

        $payload = DeveloperStatsPage::for($request, realm())->payload();
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return Response::json($json);
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
        $response->setContentDisposition(BinaryFileResponse::DISPOSITION_INLINE, basename($requestedFile));

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
        return MimeType::guess((string) $file);
    }

    private function resolvePathWithinBase(string $baseDir, string $relativePath): ?string
    {
        return SafePath::resolveFileWithinBase($baseDir, $relativePath);
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
        $response->setContentDisposition(BinaryFileResponse::DISPOSITION_INLINE, basename($requestedFile));

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
        $response->setContentDisposition(BinaryFileResponse::DISPOSITION_INLINE, basename($requestedFile));

        return $response;
    }

    private function addPublicAssetRoutes(): static
    {
        $this->defaultRoutes->add(
            'public_assets',
            RouteObject::get('/assets/{path}')
                ->options(['path' => '.+'])
                ->controller(['_controller' => DefaultRoutes::class . '::publicAssetsRouter'])
                ->build()
        );

        return $this;
    }

    public function publicAssetsRouter(Request $request): Response | BinaryFileResponse
    {
        $_path = (string) $request->attributes->get('path');
        $publicBase = alias(DIRECTORIES::PUBLIC_DIR->name) . DIRECTORY_SEPARATOR . 'assets';
        $requestedFile = $this->resolvePathWithinBase($publicBase, $_path);

        if ($requestedFile === null) {
            return $this->errorMessage($request, 404, 'Asset not found');
        }

        $response = new BinaryFileResponse($requestedFile);
        $response->headers->set('Content-Type', $this->guessMimeType($requestedFile));
        $response->setContentDisposition(BinaryFileResponse::DISPOSITION_INLINE, basename($requestedFile));

        return $response;
    }

    private function addSpaFallbackRoute(): static
    {
        if (!spaFallbackEnabled()) {
            return $this;
        }

        $this->defaultRoutes->add(
            'spa_fallback',
            RouteObject::get('/{path}')
                ->requires(['path' => '.+'])
                ->controller(['_controller' => DefaultRoutes::class . '::spaFallbackRouter'])
                ->build()
        );

        return $this;
    }

    public function spaFallbackRouter(Request $request): Response | BinaryFileResponse
    {
        $path = (string) $request->attributes->get('path');

        if ($this->isReservedSpaPath($path)) {
            return $this->errorMessage($request, 404, 'Resource not found or did not match any endpoints');
        }

        $publicBase = alias(DIRECTORIES::PUBLIC_DIR->name);
        $requestedFile = $this->resolvePathWithinBase($publicBase, $path);

        if ($requestedFile !== null && is_file($requestedFile)) {
            $response = new BinaryFileResponse($requestedFile);
            $response->headers->set('Content-Type', $this->guessMimeType($requestedFile));
            $response->setContentDisposition(BinaryFileResponse::DISPOSITION_INLINE, basename($requestedFile));

            return $response;
        }

        $index = $this->resolvePathWithinBase($publicBase, 'index.html');
        if ($index !== null && is_file($index)) {
            return new Response(
                (string) file_get_contents($index),
                200,
                ['Content-Type' => 'text/html; charset=UTF-8']
            );
        }

        return $this->errorMessage($request, 404, 'Resource not found or did not match any endpoints');
    }

    private function isReservedSpaPath(string $path): bool
    {
        $normalized = ltrim(strtolower($path), '/');

        foreach (['api/', '__pionia/', 'static/', 'media/', 'docs', 'stats'] as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

}
