<?php

namespace Pionia\TestSuite\Helpers;

use Pionia\Auth\ContextUserObject;
use Pionia\Base\EnvResolver;

trait HelperMocksTrait
{
    /**
     * This would be mimicking a user we got from our db
     * @param ContextUserObject|null $customObj
     * @return ContextUserObject
     */
    public function createMockContextUser(?ContextUserObject $customObj = null): ContextUserObject
    {
        if ($customObj) {
            return $customObj;
        } else {
            $contextUser = new ContextUserObject();
            $contextUser->user = (object)[
                'name' => 'Pionia',
                'username' => '@pionia',
                'id' => 100,
                'password' => '@password1234',
                'role_code' => 'admin',
                'created_at' => time()
            ];

            $contextUser->authenticated = true;
            $contextUser->permissions = [
                'create_users',
                'manage_system',
                'administrator'
            ];
            $contextUser->authExtra['role'] = $contextUser->user->role_code;
            return $contextUser;
        }
    }

    public function createMockSettings(): EnvResolver
    {
        $envResolver = new EnvResolver();
        $envResolver->resolve('database.ini');
        return $envResolver;
    }
}
