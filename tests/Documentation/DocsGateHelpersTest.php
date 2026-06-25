<?php

namespace Documentation;

use Pionia\TestSuite\Concerns\InteractsWithTestEnvironment;
use Pionia\TestSuite\PioniaTestCase;

class DocsGateHelpersTest extends PioniaTestCase
{
    use InteractsWithTestEnvironment;

    protected function tearDown(): void
    {
        $this->clearDocsEnv();
        parent::tearDown();
    }

    public function testDocsEnabledFollowsDebugByDefault(): void
    {
        $this->clearDocsEnv();
        $this->setDebugEnv(true);
        $this->assertTrue(apiDocsEnabled());

        $this->setDebugEnv(false);
        $this->assertFalse(apiDocsEnabled());
    }

    public function testDocsEnabledExplicitOverride(): void
    {
        $this->setDebugEnv(false);
        $this->setDocsEnv(true);
        $this->assertTrue(apiDocsEnabled());

        $this->setDocsEnv(false);
        $this->assertFalse(apiDocsEnabled());
    }

    public function testDocsTokenAuthorization(): void
    {
        $this->setDocsEnv(null, 'secret-docs');

        $authorized = \Pionia\Http\Request\Request::create('/docs/openapi.json?token=secret-docs', 'GET');
        $denied = \Pionia\Http\Request\Request::create('/docs/openapi.json', 'GET');
        $header = \Pionia\Http\Request\Request::create('/docs/openapi.json', 'GET', [], [], [], [
            'HTTP_X_DOCS_TOKEN' => 'secret-docs',
        ]);

        $this->assertTrue(apiDocsAuthorized($authorized));
        $this->assertTrue(apiDocsAuthorized($header));
        $this->assertFalse(apiDocsAuthorized($denied));
    }
}
