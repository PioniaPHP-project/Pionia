<?php

namespace Validations;

use Pionia\Collections\Arrayable;
use Pionia\Exceptions\ValidationException;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\ApiResponse;
use Pionia\Http\Services\Service;
use Pionia\TestSuite\PioniaTestCase;
use Pionia\Validations\ActionValidationResolver;
use Pionia\Validations\Attributes\Validated;
use Pionia\Validations\Attributes\ValidateField;
use ReflectionMethod;

class ActionValidationAttributeTest extends PioniaTestCase
{
    public function testResolverMergesValidatedAndValidateFieldAttributes(): void
    {
        $method = new ReflectionMethod(AnnotatedValidationService::class, 'mergeAction');

        $rules = ActionValidationResolver::resolve($method);

        $this->assertSame('required|email', $rules['email']);
        $this->assertSame('required|string|min:2', $rules['name']);
    }

    public function testValidatedAttributeRunsBeforeActionBody(): void
    {
        $service = $this->serviceWithPayload(['email' => 'not-an-email']);

        $this->expectException(ValidationException::class);

        $service->processAction('register', 'auth');
    }

    public function testValidateFieldAttributesRunBeforeActionBody(): void
    {
        $service = $this->serviceWithPayload(['name' => 'a']);

        $this->expectException(ValidationException::class);

        $service->processAction('create', 'auth');
    }

    public function testAnnotatedActionProceedsWhenDataIsValid(): void
    {
        $service = $this->serviceWithPayload([
            'email' => 'user@example.com',
        ]);

        $response = $service->processAction('register', 'auth');

        $this->assertSame(0, $response->data->get('returnCode'));
    }

    private function serviceWithPayload(array $payload): AnnotatedValidationService
    {
        $request = Request::create('/api/v1/', 'POST', [
            'service' => 'auth',
            'action' => 'register',
            ...$payload,
        ]);

        return new AnnotatedValidationService($request);
    }
}

final class AnnotatedValidationService extends Service
{
    #[Validated(rules: ['email' => 'required|email'])]
    public function registerAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }

    #[Validated(rules: ['email' => 'required'])]
    #[ValidateField('email', 'email')]
    #[ValidateField('name', 'required|string|min:2')]
    public function mergeAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }

    #[ValidateField('name', 'required|string|min:2')]
    public function createAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }
}
