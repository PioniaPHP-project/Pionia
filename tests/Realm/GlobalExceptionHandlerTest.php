<?php

namespace Realm;

use Exception;
use Pionia\Exceptions\ResourceNotFoundException;
use Pionia\Exceptions\UserUnauthenticatedException;
use Pionia\Exceptions\UserUnauthorizedException;
use Pionia\Http\Request\Request;
use Pionia\Realm\GlobalExceptionHandler;
use Pionia\TestSuite\AssertsPioniaResponses;
use Pionia\TestSuite\Concerns\InteractsWithTestEnvironment;
use Pionia\TestSuite\PioniaTestCase;

class GlobalExceptionHandlerTest extends PioniaTestCase
{
    use AssertsPioniaResponses;
    use InteractsWithTestEnvironment;
    private GlobalExceptionHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new GlobalExceptionHandler();
    }

    public function testMapsResourceNotFoundTo404(): void
    {
        $response = ($this->handler)(new ResourceNotFoundException('missing'), $this->request);
        $this->assertSame(404, $this->decodeApiResponse($response)['returnCode']);
    }

    public function testMapsUnauthenticatedTo401(): void
    {
        $response = ($this->handler)(new UserUnauthenticatedException('login required'), $this->request);
        $this->assertSame(401, $this->decodeApiResponse($response)['returnCode']);
    }

    public function testMapsUnauthorizedTo403(): void
    {
        $response = ($this->handler)(new UserUnauthorizedException('forbidden'), $this->request);
        $this->assertSame(403, $this->decodeApiResponse($response)['returnCode']);
    }

    public function testMapsGenericExceptionTo500(): void
    {
        $response = ($this->handler)(new Exception('boom'), $this->request);
        $this->assertSame(500, $this->decodeApiResponse($response)['returnCode']);
    }

    public function testHidesMessageWhenNotInDebugMode(): void
    {
        $previous = $this->captureDebugEnv();

        try {
            $this->setDebugEnv(false);
            $response = ($this->handler)(new Exception('secret details'), Request::create('/'));
            $payload = $this->decodeApiResponse($response);
            $this->assertSame('An unexpected error occurred.', $payload['returnMessage']);
        } finally {
            $this->restoreDebugEnv($previous);
        }
    }

    public function testShowsMessageInDebugMode(): void
    {
        $previous = $this->captureDebugEnv();

        try {
            $this->setDebugEnv(true);
            $response = ($this->handler)(new Exception('secret details'), Request::create('/'));
            $payload = $this->decodeApiResponse($response);
            $this->assertSame('secret details', $payload['returnMessage']);
        } finally {
            $this->restoreDebugEnv($previous);
        }
    }

}
