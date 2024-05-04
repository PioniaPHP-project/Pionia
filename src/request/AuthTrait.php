<?php

namespace jetPhp\request;

use jetPhp\core\helpers\ContextUserObject;
use jetPhp\exceptions\UserUnauthenticatedException;
use jetPhp\exceptions\UserUnauthorizedException;

trait AuthTrait
{

    /**
     * This method is used to check if the currently logged in user has the required permission to access a resource.
     *
     * It checks if user is logged in first, then checks if there are any permissions set for the user.
     *
     * And finally checks if the set permissions contain the given permission we are looking for.
     *
     * @param Request $request The request object
     * @param string|array $permission The permission to check for
     * @param string|null $message The message to be returned if the user does not have the required permission
     *
     * @return bool Returns true if the user has the required permission
     * @throws UserUnauthenticatedException
     * @throws UserUnauthorizedException
     */
    public static function canAny(Request $request, string | array $permission, ?string $message = 'You do not have access to this resource'): bool
    {
        // check if the user is authenticated even before checking for permissions
        self::mustAuthenticate($request);

        $message = $message ?? 'You do not have access to this resource';

        // if the user has no permissions at all
        if (is_null($request->getAuth()->permissions)) {
            throw new UserUnauthorizedException('You must be authorised to access this resource');
        }

        // if the user has any of the permissions
        if (is_array($permission)) {
            foreach ($permission as $perm) {
                if (in_array($perm, $request->getAuth()->permissions)) {
                    return true;
                }
            }
            throw new UserUnauthorizedException($message);
        }

        // if the user has the permission
        if (!in_array($permission, $request->getAuth()->permissions)) {
            throw new UserUnauthorizedException($message);
        }
        return true;
    }


    /**
     * Like CanAny but only check for one permission at a time
     *
     * @param Request $request The request object
     * @param string $permission The permission to check for
     * @param string|null $message The message to be returned if the user does not have the required permission
     *
     * @return bool Returns true if the user has the required permission
     * @throws UserUnauthenticatedException
     * @throws UserUnauthorizedException
     */
    public static function can(Request $request, string $permission, ?string $message = 'You do not have access to this resource'): bool
    {
        // check if the user is authenticated even before checking for permissions
        self::mustAuthenticate($request);

        $message = $message ?? 'You do not have access to this resource';

        // if the user has no permissions at all
        if (is_null($request->getAuth()->permissions)) {
            throw new UserUnauthorizedException('You must be authorised to access this resource');
        }

        // if the user has the permission
        if (!in_array($permission, $request->getAuth()->permissions)) {
            throw new UserUnauthorizedException($message);
        }
        return true;
    }

    /**
     * Similar to canAny only that this checks if the user has all the passed permissions
     * @param Request $request The request object
     * @param array $permissions The permissions to check for
     * @param string|null $message The message to be returned if the user does not have the required permission
     * @return bool Returns true if the user has the required permission, else returns a BaseResponse object
     * @throws UserUnauthenticatedException If the user is not authenticated
     * @throws UserUnauthorizedException If the user does not have the required permission
     */
    public static function canAll(Request $request, array $permissions, ?string $message = 'You do not have access to this resource'): bool
    {
        // check if the user is authenticated even before checking for permissions
        self::mustAuthenticate($request);

        $message = $message ?? 'You do not have access to this resource';

        // if the user has no permissions at all
        if (is_null($request->getAuth()->permissions)) {
            throw new UserUnauthorizedException('You must be authorised to access this resource');
        }

        // if the user has any of the permissions
        foreach ($permissions as $perm) {
            if (!in_array($perm, $request->getAuth()->permissions)) {
                throw new UserUnauthorizedException($message);
            }
        }
        return true;
    }


    /**
     * This method holds the currently logged in user object
     * @param Request $request The request object
     * @return ContextUserObject|null The currently logged in user object or null if no user is logged in
     */
    public static function auth(Request $request): ?ContextUserObject
    {
        return $request->getAuth();
    }

    /**
     * This method ensures that only authenticated users can access a resource
     *
     * @param Request $request
     * @param string|null $message Use this to override the default message
     * @return bool
     * @throws UserUnauthenticatedException
     */
    public static function mustAuthenticate(Request $request, ?string $message = 'You must be authenticated to access this resource'): bool
    {
        if (is_null(self::auth($request)) || !self::auth($request)->authenticated){
            throw new UserUnauthenticatedException($message);
        }
        return true;
    }

    /**
     * Checks if the auth extra data contains a key or not
     *
     * @param Request $request
     * @param string $key
     * @return bool Returns true if the key is present in the authExtra data else returns false
     */
    public static function authExtraHas(Request $request, string $key): bool
    {
        if (!self::auth($request)->authExtra){
            return false;
        }
        return array_key_exists($key, self::auth($request)->authExtra);
    }


    /**
     * Returns the auth extra data by key
     *
     * @param Request $request
     * @param string $key
     * @return mixed|null Returns the value of the key if it exists else returns null
     */
    public static function getAuthExtraByKey(Request $request, string $key): mixed
    {
        if (self::authExtraHas($request, $key)){
            return self::auth($request)->authExtra[$key];
        }
        return null;
    }


}
