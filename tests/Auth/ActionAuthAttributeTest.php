<?php

namespace Auth;

use Pionia\Auth\ActionAuthResolver;
use Pionia\Auth\Attributes\Authenticated;
use Pionia\Auth\Attributes\Can;
use Pionia\Auth\Attributes\CanAny;
use Pionia\Auth\ContextUserObject;
use Pionia\Collections\Arrayable;
use Pionia\Exceptions\UserUnauthenticatedException;
use Pionia\Exceptions\UserUnauthorizedException;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\ApiResponse;
use Pionia\Http\Services\Service;
use Pionia\TestSuite\PioniaTestCase;
use ReflectionClass;
use ReflectionMethod;

class ActionAuthAttributeTest extends PioniaTestCase
{
    public function testMethodAuthenticatedBlocksAnonymous(): void
    {
        $service = $this->service(MethodAuthService::class);

        $this->expectException(UserUnauthenticatedException::class);

        $service->processAction('create', 'task');
    }

    public function testMethodAuthenticatedAllowsAuthenticatedUser(): void
    {
        $service = $this->service(MethodAuthService::class, authenticated: true);

        $response = $service->processAction('create', 'task');

        $this->assertSame(0, $response->data->get('returnCode'));
    }

    public function testServiceAuthenticatedWithExceptAllowsExemptAction(): void
    {
        $service = $this->service(ServiceAuthWithExcept::class);

        $response = $service->processAction('login', 'member');

        $this->assertSame(0, $response->data->get('returnCode'));
    }

    public function testServiceAuthenticatedBlocksNonExemptAction(): void
    {
        $service = $this->service(ServiceAuthWithExcept::class);

        $this->expectException(UserUnauthenticatedException::class);

        $service->processAction('profile', 'member');
    }

    public function testCanStringRequiresPermission(): void
    {
        $service = $this->service(PermissionService::class, authenticated: true, permissions: ['task.list']);

        $this->expectException(UserUnauthorizedException::class);

        $service->processAction('create', 'task');
    }

    public function testCanStringAllowsMatchingPermission(): void
    {
        $service = $this->service(PermissionService::class, authenticated: true, permissions: ['task.create']);

        $response = $service->processAction('create', 'task');

        $this->assertSame(0, $response->data->get('returnCode'));
    }

    public function testCanArrayRequiresAllPermissions(): void
    {
        $service = $this->service(PermissionService::class, authenticated: true, permissions: ['task.update']);

        $this->expectException(UserUnauthorizedException::class);

        $service->processAction('update', 'task');
    }

    public function testCanAnyAllowsOneOfListedPermissions(): void
    {
        $service = $this->service(PermissionService::class, authenticated: true, permissions: ['task.admin']);

        $response = $service->processAction('patch', 'task');

        $this->assertSame(0, $response->data->get('returnCode'));
    }

    public function testCanAnyRejectsWhenNoneMatch(): void
    {
        $service = $this->service(PermissionService::class, authenticated: true, permissions: ['task.list']);

        $this->expectException(UserUnauthorizedException::class);

        $service->processAction('patch', 'task');
    }

    public function testLegacyServiceRequiresAuthStillWorks(): void
    {
        $service = $this->service(LegacyAuthService::class);

        $this->expectException(UserUnauthenticatedException::class);

        $service->processAction('list', 'task');
    }

    public function testDocInferenceFromAttributes(): void
    {
        $class = new ReflectionClass(ServiceAuthWithExcept::class);
        $login = new ReflectionMethod(ServiceAuthWithExcept::class, 'loginAction');
        $profile = new ReflectionMethod(ServiceAuthWithExcept::class, 'profileAction');

        $this->assertFalse(ActionAuthResolver::infersRequiredAuth($class, $login, 'login'));
        $this->assertTrue(ActionAuthResolver::infersRequiredAuth($class, $profile, 'profile'));

        $create = new ReflectionMethod(PermissionService::class, 'createAction');
        $this->assertSame(
            ['task.create'],
            ActionAuthResolver::inferredPermissions(new ReflectionClass(PermissionService::class), $create, 'create'),
        );
    }

    /**
     * @param class-string<Service> $class
     * @param list<string>|null $permissions
     */
    private function service(string $class, bool $authenticated = false, ?array $permissions = null): Service
    {
        $request = Request::create('/api/v1/', 'POST', [
            'service' => 'task',
            'action' => 'create',
        ]);

        if ($authenticated) {
            $user = new ContextUserObject();
            $user->authenticated = true;
            $user->user = (object) ['id' => 1, 'username' => 'alex'];
            $user->permissions = $permissions ?? ['task.create', 'task.update', 'task.admin'];
            $request->setAuthenticationContext($user);
        }

        return new $class($request);
    }
}

final class MethodAuthService extends Service
{
    #[Authenticated]
    public function createAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }
}

#[Authenticated(except: ['login'])]
final class ServiceAuthWithExcept extends Service
{
    public function loginAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }

    public function profileAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }
}

final class PermissionService extends Service
{
    #[Can('task.create')]
    public function createAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }

    #[Can(['task.update', 'task.admin'])]
    public function updateAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }

    #[CanAny(['task.edit', 'task.admin'])]
    public function patchAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }
}

final class LegacyAuthService extends Service
{
    public bool $serviceRequiresAuth = true;

    public function listAction(Arrayable $data): ApiResponse
    {
        return response(0, 'ok', null);
    }
}
