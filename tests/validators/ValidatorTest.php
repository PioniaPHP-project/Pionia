<?php

namespace validators;

use Pionia\TestSuite\ContextFreeTestCase;
use Pionia\Validators\Validator;
use function PHPUnit\Framework\assertEquals;

class ValidatorTest extends ContextFreeTestCase
{
    public static function testIsPhone()
    {
        $validator = new Validator(false);
        $phone = '+256781109109';
        return assertEquals($validator->asInternationalPhone($phone, '+256'), true);
    }

    public static function testIsEmail()
    {
        $validator = new Validator(false);
        $email = 'ezrajet9@gmail.com';
        return assertEquals($validator->asEmail($email), 1);
    }

    public static function testIsHttpUrl()
    {
        $validator = new Validator(false);
        $http = 'file://www.php.net';
        assertEquals($validator->asUrl($http), 1);
    }
    public static function testIsHttpsUrl()
    {
        $validator = new Validator(false);
        $https = 'https://www.php.net';
        assertEquals($validator->asUrl($https), 1);
    }

    public static function testDomain()
    {
        $validator = new Validator(false);
        $domain = 'me.com';
        assertEquals($validator->asDomain($domain), 1);
    }

    public static function testSlug()
    {
        $validator = new Validator(false);
        $slug = 'mesjfsksdfsjdlf';
        assertEquals($validator->asSlug($slug), 1);
    }
}
