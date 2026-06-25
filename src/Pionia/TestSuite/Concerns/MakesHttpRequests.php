<?php

namespace Pionia\TestSuite\Concerns;

use Pionia\Http\Base\WebKernel;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\TestResponse;

trait MakesHttpRequests
{
    protected function dispatchRequest(Request $request): TestResponse
    {
        $kernel = app()->make(WebKernel::class);

        ob_start();
        try {
            $response = $kernel->handle($request);
        } finally {
            ob_end_clean();
        }

        return new TestResponse($response);
    }

    protected function get(string $uri, array $server = []): TestResponse
    {
        return $this->dispatchRequest(Request::create($uri, 'GET', [], [], [], $server));
    }

    protected function post(string $uri, array $data = [], array $server = []): TestResponse
    {
        return $this->dispatchRequest(Request::create($uri, 'POST', $data, [], [], $server));
    }

    protected function postApi(string $service, string $action, array $data = [], ?string $version = null): TestResponse
    {
        $payload = array_merge(['service' => $service, 'action' => $action], $data);

        return $this->post(apiVersionPath($version), $payload);
    }

    protected function getApiPing(?string $version = null): TestResponse
    {
        return $this->get(apiPingPath($version));
    }
}
