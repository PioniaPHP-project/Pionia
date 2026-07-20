<?php

namespace Auth;

use Pionia\Auth\JwtAuthentication;
use Pionia\Http\Request\Request;
use Pionia\TestSuite\PioniaTestCase;

class JwtAuthenticationTest extends PioniaTestCase
{
    public function testAuthenticatesValidBearerJwt(): void
    {
        setEnv('JWT_SECRET', 'jwt-test-secret');
        $token = jwt_encode(['sub' => '42', 'username' => 'jet', 'permissions' => ['admin']], 'jwt-test-secret');
        $request = Request::create('/api/v1/', 'POST');
        $request->headers->set('Authorization', 'Bearer ' . $token);

        $user = (new JwtAuthentication())->authenticate($request);

        $this->assertNotNull($user);
        $this->assertTrue($user->authenticated);
        $this->assertSame('42', $user->user->id);
        $this->assertSame('jet', $user->user->username);
        $this->assertSame(['admin'], $user->permissions);
    }

    public function testRejectsMissingOrInvalidBearer(): void
    {
        $auth = new JwtAuthentication();
        $bare = Request::create('/api/v1/', 'POST');
        $this->assertNull($auth->authenticate($bare));

        $bad = Request::create('/api/v1/', 'POST');
        $bad->headers->set('Authorization', 'Bearer not-a-jwt');
        $this->assertNull($auth->authenticate($bad));
    }
}
