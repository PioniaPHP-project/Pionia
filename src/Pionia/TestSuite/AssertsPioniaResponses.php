<?php

namespace Pionia\TestSuite;

use Pionia\Collections\Arrayable;
use Pionia\Http\Response\ApiResponse;
use Pionia\Realm\AppRealm;
use PHPUnit\Framework\Assert;

trait AssertsPioniaResponses
{
    protected function decodeApiResponse(ApiResponse $response): array
    {
        return json_decode($response->getPrettyResponse(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function assertPioniaOk(TestResponse | ApiResponse $response): void
    {
        $payload = $this->responsePayload($response);
        Assert::assertSame(0, $payload['returnCode'] ?? null, 'Expected returnCode 0');
    }

    protected function assertPioniaError(TestResponse | ApiResponse $response, int $returnCode): void
    {
        $payload = $this->responsePayload($response);
        Assert::assertSame($returnCode, $payload['returnCode'] ?? null);
    }

    /**
     * @param list<string> $keys
     */
    protected function assertJsonStructure(array $keys, TestResponse | ApiResponse $response): void
    {
        $payload = $this->responsePayload($response);
        foreach ($keys as $key) {
            Assert::assertArrayHasKey($key, $payload, "Missing key: {$key}");
        }
    }

    protected function ensureSwitchContextIsArrayable(): void
    {
        $app = app();
        $switches = $app->getSilently(AppRealm::SWITCHES_TAGS);
        $services = $app->getSilently(AppRealm::SERVICES_TAG);

        $switchData = $switches instanceof Arrayable ? $switches->all() : (array) ($switches ?? []);
        $serviceData = $services instanceof Arrayable ? $services->all() : (array) ($services ?? []);

        $app->set(AppRealm::SWITCHES_TAGS, arr($switchData));
        $app->set(AppRealm::SERVICES_TAG, arr($serviceData));
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(TestResponse | ApiResponse $response): array
    {
        if ($response instanceof TestResponse) {
            return $response->pioniaPayload();
        }

        return $this->decodeApiResponse($response);
    }
}
