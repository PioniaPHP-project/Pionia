<?php

namespace Realm;

use Pionia\TestSuite\PioniaTestCase;

class ExceptionHandlerRegistrationTest extends PioniaTestCase
{
    public function testExceptionHandlerIsRegistered(): void
    {
        if (!function_exists('get_exception_handler')) {
            $this->markTestSkipped('get_exception_handler() requires PHP 8.5+');
        }

        $handler = get_exception_handler();
        $this->assertNotNull($handler);
        $this->assertTrue(is_callable($handler));
    }

    public function testErrorHandlerIsRegistered(): void
    {
        if (!function_exists('get_error_handler')) {
            $this->markTestSkipped('get_error_handler() requires PHP 8.5+');
        }

        $handler = get_error_handler();
        $this->assertNotNull($handler);
        $this->assertTrue(is_callable($handler));
    }
}
