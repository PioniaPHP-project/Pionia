<?php

namespace validators;

use Pionia\TestSuite\ContextFreeTestCase;
use Pionia\Validators\Validator;
use function PHPUnit\Framework\assertEquals;

class ValidatorTest extends ContextFreeTestCase
{
    public function testValidator()
    {
        assertEquals(true, Validator::is('sample@gmail.com', 'email'));
    }
}
