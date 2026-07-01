<?php

namespace Application\Authentications;

use Pionia\Auth\Authentication;
use Pionia\Auth\ContextUserObject;
use Pionia\Http\Request\Request;

/**
 * Demo auth: send header `Authorization: Bearer demo-token` to authenticate.
 */
class DemoAuthentication extends Authentication
{
    public function authenticate(Request $request): ?ContextUserObject
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));
        if ($token !== 'demo-token') {
            return null;
        }

        $user = new ContextUserObject();
        $user->user = (object) [
            'id' => 'demo-user',
            'username' => 'demo',
        ];
        $user->authenticated = true;
        $user->permissions = ['read', 'write'];

        return $user;
    }
}
