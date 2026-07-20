<?php

namespace Pionia\Auth;

use Pionia\Http\Request\Request;

/**
 * Bearer JWT authentication backend.
 *
 * Register via `[app_authentications]` or a provider's `authentications()` hook.
 * Expects `Authorization: Bearer <jwt>`. Claims map to {@see ContextUserObject}:
 * - `sub` → user id (and username fallback)
 * - `permissions` / `perms` → permissions list
 * - remaining claims → `authExtra`
 *
 * @see \Pionia\Security\Security::jwtDecode()
 */
class JwtAuthentication extends Authentication
{
    public function authenticate(Request $request): ?ContextUserObject
    {
        $header = (string) $request->headers->get('Authorization', '');
        if (!str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            return null;
        }

        try {
            $decoded = security()->jwtDecode($token, true);
        } catch (\Throwable) {
            return null;
        }

        $payload = $decoded['payload'];
        $user = new ContextUserObject();
        $sub = $payload['sub'] ?? $payload['id'] ?? null;
        $user->user = (object) [
            'id' => $sub,
            'username' => $payload['username'] ?? $payload['email'] ?? $sub,
        ];
        $user->authenticated = true;

        $permissions = $payload['permissions'] ?? $payload['perms'] ?? [];
        $user->permissions = is_array($permissions) ? array_values($permissions) : [];

        $extra = $payload;
        unset($extra['permissions'], $extra['perms'], $extra['sub'], $extra['id'], $extra['username'], $extra['email']);
        $user->authExtra = $extra;

        return $user;
    }
}
