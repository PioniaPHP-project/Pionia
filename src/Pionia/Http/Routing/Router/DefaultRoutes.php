<?php

namespace Pionia\Http\Routing;

use DIRECTORIES;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Realm\AppRealm;
use Pionia\Templating\TemplateEngineInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\RouteCollection;

class DefaultRoutes
{
    private RouteCollection  $defaultRoutes;
    private AppRealm  $appRealm;

    public function __construct(AppRealm $realm)
    {
        $this->appRealm = $realm;
        $this->defaultRoutes = new RouteCollection();
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
            ->controller([DefaultRoutes::class, 'homeResolver'])
            ->build()
        );
        return $this;
    }

    /**
     * Adds the default routes for the homepage, statics files and html
     * @return $this
     */
    public function collect(): static
    {
       $this->addRouteForHome()
            ->addRouteForMediaFiles()
            ->addStaticFiles()
            ->otherStaticFilesRouter();

       $this->appRealm->updateCache(AppRealm::APP_ROUTES_TAG, $this->defaultRoutes, true);
       $this->appRealm->set(AppRealm::APP_ROUTES_TAG, $this->defaultRoutes);
       return $this;
    }

    private function homeResolver(Request $request): Response
    {
        // check if we have an index.html in the static folder otherwise, serve the inbuilt html
        $fileManager = new Filesystem();
        $staticPage = path(directoryFor(DIRECTORIES::STATIC_DIR->name).DIRECTORY_SEPARATOR.'index.html');
        $response = new Response();

        if ($fileManager->exists($staticPage)) {
            // send the file here
            $response->setContent($fileManager->readFile($staticPage));
        } else {
            $welcomePage = path(DIRECTORIES::WELCOME_PAGE->name);
            if ($fileManager->exists($welcomePage)) {
                $content = app()->getSilently(TemplateEngineInterface::class)?->view($welcomePage, [
                    'app' => realm(),
                    'request' => $request,
                ]);

            }
        }
        $response =  new Response($content, 200, ['Content-Type' => 'text/html']);
        return $response->prepare($request)->send();
    }

    /**
     * Resolves all files served in the static folder
     * @return $this
     */
    private function otherStaticFilesRouter(): static
    {
        $this->defaultRoutes->add(
            'statics', RouteObject::get('/{path}')
            ->options(['path' => '.+'])
            ->controller([DefaultRoutes::class, 'staticFilesRouter'])
            ->build()
        );
        return $this;
    }

    /**
     * Serve any other files in the static folder
     * @param Request $request
     * @return $this
     */
    private function staticFilesRouter(Request $request): static
    {
        $fileManager = new Filesystem();
        $_path = $request->attributes->get('path');
        $requestedFile = path(DIRECTORIES::STATIC_DIR->name.DIRECTORY_SEPARATOR.$_path);


        if (!$requestedFile || !$fileManager->exists($requestedFile)) {
            $response = new Response("File not found", 404);
            $response->send();
            return $this;
        }

        // Get MIME type
        $mimeInstance = new MimeTypes();
        $mime = $mimeInstance->guessMimeType($requestedFile);

        // Serve a file
        $response = new BinaryFileResponse($requestedFile);
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($requestedFile));
        $response->send();

        return $this;
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
            ->controller([DefaultRoutes::class, "staticFilesRouter"])
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
            ->controller([DefaultRoutes::class, 'mediaFilesRouter'])
            ->options(['path' => '.+'])
            ->build());
        return $this;
    }

    private function mediaFilesRouter(Request $request): static
    {
        // Get the dynamic {path} parameter from the route
        $path = $request->attributes->get('path');

        // Base directory for static assets (e.g., /your_project/static/)
        $baseDir = path(DIRECTORIES::STATIC_DIR->name);

        // Build the full file path
        $requestedFile = path($baseDir . DIRECTORY_SEPARATOR . $path);

        // Security check to prevent directory traversal
        if (
            !$requestedFile ||
            !str_starts_with($requestedFile, realpath($baseDir)) ||
            !is_file($requestedFile)
        ) {
            $response = new Response("File not found", 404);
            $response->send();
            return $this;
        }

        // Determine content type (MIME)
        $mime = mime_content_type($requestedFile) ?: 'application/octet-stream';

        // Serve the file
        $response = new BinaryFileResponse($requestedFile);
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($requestedFile));
        $response->send();

        return $this;
    }

}
