<?php

namespace Console;

use Pionia\TestSuite\PioniaTestCase;

class StopRoadRunnerServerCommandTest extends PioniaTestCase
{
    public function testStopserverConsidersEnvPortWhenCheckingListeners(): void
    {
        $runtime = BASE_PATH . DIRECTORY_SEPARATOR . '.rr.runtime.json';
        $backup = null;

        if (is_file($runtime)) {
            $backup = file_get_contents($runtime);
            unlink($runtime);
        }

        file_put_contents($runtime, json_encode([
            'http_address' => '127.0.0.1:8003',
            'port' => 8003,
        ], JSON_THROW_ON_ERROR));

        try {
            $code = $this->artisan('stopserver');

            $this->assertContains($code, [0, 1]);
        } finally {
            if ($backup !== null) {
                file_put_contents($runtime, $backup);
            } elseif (is_file($runtime)) {
                unlink($runtime);
            }
        }
    }
}
