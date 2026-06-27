<?php

namespace Feature;

use Pionia\TestSuite\PioniaTestCase;

class ExampleMiddlewareTest extends PioniaTestCase
{
    public function testRequestIdMiddlewareAddsHeaderOnPing(): void
    {
        $response = $this->getApiPing();

        $this->assertNotEmpty($response->header('X-Request-Id'));
    }

    public function testRequestIdIsStableForSameRequestObjectPath(): void
    {
        $customId = 'test-request-id-' . uniqid('', true);
        $response = $this->get(apiPingPath(), [
            'HTTP_X_REQUEST_ID' => $customId,
        ]);

        $this->assertSame($customId, $response->header('X-Request-Id'));
    }
}
