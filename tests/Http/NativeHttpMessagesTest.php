<?php

namespace Http;

use Pionia\Http\Bag\ParameterBag;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;
use Pionia\Http\UploadedFile;
use Pionia\TestSuite\PioniaTestCase;

class NativeHttpMessagesTest extends PioniaTestCase
{
    public function testRequestCreateParsesJsonPayload(): void
    {
        $request = Request::create(
            '/api/v1/',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{"service":"auth","action":"ping"}',
        );

        $this->assertSame('json', $request->getContentTypeFormat());
        $this->assertSame('auth', $request->getPayload()->get('service'));
        $this->assertSame('ping', $request->getData()->get('action'));
    }

    public function testRequestCreateHandlesPostFormParameters(): void
    {
        $request = Request::create('/api/v1/', 'POST', [
            'service' => 'auth',
            'action' => 'list_auth',
        ]);

        $this->assertSame('auth', $request->get('service'));
        $this->assertSame('list_auth', $request->getPayload()->get('action'));
    }

    public function testParameterBagGetString(): void
    {
        $bag = new ParameterBag(['name' => 'pionia', 'count' => 3]);

        $this->assertSame('pionia', $bag->getString('name'));
        $this->assertSame('', $bag->getString('missing'));
        $this->assertSame('fallback', $bag->getString('missing', 'fallback'));
    }

    public function testUploadedFileMoveCreatesTarget(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'pionia_native_');
        file_put_contents($source, 'payload');

        $upload = new UploadedFile($source, 'demo.txt', 'text/plain', UPLOAD_ERR_OK, true);
        $targetDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pionia_upload_test_' . uniqid();
        mkdir($targetDir);

        $moved = $upload->move($targetDir, 'saved.txt');

        $this->assertFileExists($moved->getPathname());
        $this->assertSame('saved.txt', $moved->getClientOriginalName());

        unlink($moved->getPathname());
        rmdir($targetDir);
    }

    public function testResponseJsonFactory(): void
    {
        $response = Response::json('{"ok":true}', 201);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('application/json; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertSame('{"ok":true}', $response->getContent());
    }
}
