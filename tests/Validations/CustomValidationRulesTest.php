<?php

namespace Validations;

use Pionia\Collections\Arrayable;
use Pionia\Exceptions\ValidationException;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\Validations\Contracts\ValidationRuleContract;
use Pionia\Validations\ValidationContext;
use Pionia\Validations\ValidationManager;

class CustomValidationRulesTest extends PioniaTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EvenNumberRule::$instances = 0;
    }

    private function data(array $payload): Arrayable
    {
        return new Arrayable($payload);
    }

    public function testExtendCallableRuleWorksInRulesPipe(): void
    {
        validations()->extend('test_kenya_phone', function (ValidationContext $ctx): void {
            $phone = (string) $ctx->value();
            if (!preg_match('/^\+254/', $phone)) {
                $ctx->fail('Phone must be a Kenyan number');
            }
        });

        rules($this->data(['phone' => '+254712345678']), [
            'phone' => 'required|test_kenya_phone',
        ]);

        $this->assertTrue(true);
    }

    public function testExtendCallableRuleFailsWithMessage(): void
    {
        validations()->extend('test_kenya_phone_fail', function (ValidationContext $ctx): void {
            if (!str_starts_with((string) $ctx->value(), '+254')) {
                $ctx->fail('Phone must be a Kenyan number');
            }
        });

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Phone must be a Kenyan number');

        rules($this->data(['phone' => '+44123456789']), [
            'phone' => 'required|test_kenya_phone_fail',
        ]);
    }

    public function testValidateChainRuleMethodUsesSharedRegistry(): void
    {
        validations()->extend('test_uppercase', function (ValidationContext $ctx): void {
            if ($ctx->value() !== strtoupper((string) $ctx->value())) {
                $ctx->fail('Value must be uppercase');
            }
        });

        validate('code', $this->data(['code' => 'ABC']))->required()->rule('test_uppercase');

        $this->assertTrue(true);
    }

    public function testExtendClassRuleIsReusedAsSingleton(): void
    {
        validations()->extend('test_even', EvenNumberRule::class);

        validate('count', $this->data(['count' => 4]))->rule('test_even');

        $this->assertSame(1, EvenNumberRule::$instances);
    }

    public function testSameManagerInstanceAcrossCalls(): void
    {
        $first = validations();
        $first->extend('test_shared', static fn () => null);

        $this->assertSame($first, validations());
        $this->assertTrue(validations()->has('test_shared'));
    }
}

final class EvenNumberRule implements ValidationRuleContract
{
    public static int $instances = 0;

    public function __construct()
    {
        ++self::$instances;
    }

    public function validate(ValidationContext $context): void
    {
        $value = $context->value();
        if (!is_numeric($value) || ((int) $value) % 2 !== 0) {
            $context->fail('Value must be an even number');
        }
    }
}
