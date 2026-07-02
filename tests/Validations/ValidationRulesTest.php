<?php

namespace Validations;

use Pionia\Collections\Arrayable;
use Pionia\Exceptions\ValidationException;
use Pionia\TestSuite\PioniaTestCase;

class ValidationRulesTest extends PioniaTestCase
{
    private function data(array $payload): Arrayable
    {
        return new Arrayable($payload);
    }

    public function testRulesPipeSyntaxPasses(): void
    {
        rules($this->data([
            'email' => 'user@example.com',
            'password' => 'Secret1!',
            'password_confirmation' => 'Secret1!',
            'role' => 'admin',
            'age' => '21',
        ]), [
            'email' => 'required|email',
            'password' => 'required|password|min:8',
            'password_confirmation' => 'required|confirmed:password',
            'role' => 'required|in:admin,user',
            'age' => 'integer|min:18',
        ]);

        $this->assertTrue(true);
    }

    public function testNullableSkipsOtherRulesWhenMissing(): void
    {
        rules($this->data([
            'email' => 'user@example.com',
        ]), [
            'email' => 'required|email',
            'nickname' => 'nullable|string|min:2',
        ]);

        $this->assertTrue(true);
    }

    public function testValidateChainThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('email must be a valid email address');

        validate('email', $this->data(['email' => 'not-an-email']))->required()->email();
    }

    public function testRulesThrowsOnInvalidEmail(): void
    {
        $this->expectException(ValidationException::class);

        rules($this->data(['email' => 'bad']), [
            'email' => 'required|email',
        ]);
    }

    public function testIntegerAcceptsJsonNumericString(): void
    {
        validate('count', $this->data(['count' => '12']))->integer();

        $this->assertTrue(true);
    }

    public function testUuidRule(): void
    {
        validate('id', $this->data(['id' => '550e8400-e29b-41d4-a716-446655440000']))->uuid();

        $this->assertTrue(true);
    }

    public function testBetweenRuleForStringLength(): void
    {
        validate('code', $this->data(['code' => 'abcd']))->string()->between(2, 6);

        $this->assertTrue(true);
    }

    public function testNotInRule(): void
    {
        $this->expectException(ValidationException::class);

        rules($this->data(['status' => 'banned']), [
            'status' => 'required|not_in:banned,deleted',
        ]);
    }

    public function testArrayRuleSyntax(): void
    {
        rules($this->data(['tags' => ['a', 'b']]), [
            'tags' => ['required', 'array', 'min:1'],
        ]);

        $this->assertTrue(true);
    }
}
