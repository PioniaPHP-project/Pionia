<?php

namespace Http;

use Pionia\Http\Mime\MimeType;
use Pionia\TestSuite\PioniaTestCase;

class MimeTypeTest extends PioniaTestCase
{
    public function testGuessesCommonExtensions(): void
    {
        $this->assertSame('text/css', MimeType::guess('welcome.css'));
        $this->assertSame('application/javascript', MimeType::guess('app.js'));
        $this->assertSame('application/json', MimeType::guess('data.json'));
    }
}
