<?php

namespace Utils;

use Pionia\TestSuite\PioniaTestCase;

class RenderHelperTest extends PioniaTestCase
{
    public function testRenderToStringReturnsOutputWithoutExiting(): void
    {
        $fixture = dirname(__DIR__) . '/fixtures/hello.view';
        $html = renderToString($fixture, ['title' => 'Test Title']);

        $this->assertSame('Hello Test Title', trim($html));
    }
}
