<?php

namespace Http;

use Pionia\Http\Moonlight\MoonlightDispatcher;
use Pionia\Http\Moonlight\MoonlightJobDispatcher;
use Pionia\Http\Moonlight\MoonlightJobPayload;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;

class MoonlightDispatcherTest extends PioniaTestCase
{
    public function testDispatchPayloadInvokesRegisteredService(): void
    {
        $registry = fn () => [
            'auth' => \Application\Services\AuthService::class,
        ];

        $response = MoonlightDispatcher::dispatchPayload(
            ['service' => 'auth', 'action' => 'list_auth'],
            $registry,
        );

        $this->assertPioniaOk($response);
    }

    public function testJobPayloadRoundTrip(): void
    {
        $job = MoonlightJobPayload::fromArray([
            'service' => 'welcome',
            'action' => 'ping',
            'payload' => ['foo' => 'bar'],
        ]);

        $this->assertSame('welcome', $job->service);
        $this->assertSame('ping', $job->action);
        $this->assertSame(['foo' => 'bar'], $job->payload);
    }

    public function testJobDispatcherRunsSynchronously(): void
    {
        $job = new MoonlightJobPayload('auth', 'list_auth');
        $registry = fn () => ['auth' => \Application\Services\AuthService::class];

        $response = MoonlightJobDispatcher::dispatch($job, $registry);

        $this->assertPioniaOk($response);
    }

    public function testResolveServiceAndActionFromPostBody(): void
    {
        $request = Request::create('/api/v1/', 'POST', [
            'service' => 'auth',
            'action' => 'list_auth',
        ]);

        [$service, $action] = MoonlightDispatcher::resolveServiceAndAction($request);

        $this->assertSame('auth', $service);
        $this->assertSame('list_auth', $action);
    }
}
