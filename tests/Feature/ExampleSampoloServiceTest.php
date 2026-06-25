<?php

namespace Feature;

use Pionia\TestSuite\PioniaTestCase;

class ExampleSampoloServiceTest extends PioniaTestCase
{
    public function testSampoloServiceIsRegisteredOnMainSwitch(): void
    {
        $response = $this->postApi('sampolo', 'list');

        $payload = $response->json();
        $this->assertSame(200, $response->status());
        $this->assertIsInt($payload['returnCode']);
    }
}
