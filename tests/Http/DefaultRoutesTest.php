<?php

namespace Http;

use Pionia\Http\Request\Request;
use Pionia\Http\Routing\Router\DefaultRoutes;
use Pionia\TestSuite\PioniaTestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DefaultRoutesTest extends PioniaTestCase
{
    private DefaultRoutes $routes;

    private ?string $mediaFixture = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routes = new DefaultRoutes();

        $mediaDir = alias(\DIRECTORIES::STORAGE_DIR->name) . DIRECTORY_SEPARATOR . 'media';
        if (is_file($mediaDir)) {
            unlink($mediaDir);
        }
        if (!is_dir($mediaDir)) {
            mkdir($mediaDir, 0775, true);
        }
        $this->mediaFixture = $mediaDir . DIRECTORY_SEPARATOR . 'phase1-test.txt';
        file_put_contents($this->mediaFixture, 'phase1-media-fixture');
    }

    protected function tearDown(): void
    {
        if ($this->mediaFixture && file_exists($this->mediaFixture)) {
            unlink($this->mediaFixture);
        }
        parent::tearDown();
    }

    public function testHomeResolverReturnsHtmlResponseWithoutExiting(): void
    {
        $request = Request::create('/');
        $response = $this->routes->homeResolver($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
    }

    public function testStaticRouterServesExistingFile(): void
    {
        $request = Request::create('/static/favicon.ico');
        $request->attributes->set('path', 'favicon.ico');

        $response = $this->routes->staticFilesRouter($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testStaticRouterBlocksPathTraversal(): void
    {
        $request = Request::create('/static/../../../etc/passwd');
        $request->attributes->set('path', '../../../etc/passwd');

        $response = $this->routes->staticFilesRouter($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testMediaRouterServesFileFromStorageMedia(): void
    {
        $request = Request::create('/media/phase1-test.txt');
        $request->attributes->set('path', 'phase1-test.txt');

        $response = $this->routes->mediaFilesRouter($request);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMediaRouterBlocksPathTraversal(): void
    {
        $request = Request::create('/media/../cache/hack.txt');
        $request->attributes->set('path', '../cache/hack.txt');

        $response = $this->routes->mediaFilesRouter($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->getStatusCode());
    }
}
