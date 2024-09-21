<?php

namespace Pionia\Http\Services;

use Pionia\Auth\ContextUserObject;
use Pionia\Exceptions\UserUnauthenticatedException;
use Pionia\Exceptions\UserUnauthorizedException;

/**
 * This trait provides common authentication methods for the services
 *
 * It is used to check if the user is authenticated, has the required permissions, and other authentication-related methods
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
trait AuthTrait
{

    /**
     * This method is used to check if the currently logged in user has the required permission to access a resource.
     *
     * It checks if user is logged in first, then checks if there are any permissions set for the user.
     *
     * And finally checks if the set permissions contain the given permission we are looking for.
     *
     * @param string|array $permission The permission to check for
     * @param string|null $message The message to be returned if the user does not have the required permission
     *
     * @return bool Returns true if the user has the required permission
     * @throws UserUnauthenticatedException
     * @throws UserUnauthorizedException
     */
    public function canAny( string | array $permission, ?string $message = 'You do not have access to this resource'): bool
    {
        // check if the user is authenticated even before checking for permissions
        $this->mustAuthenticate($this->request);

        $message = $message ?? 'You do not have access to this resource';

        // if the user has no permissions at all
        if (is_null($this->request->getAuth()->permissions)) {
            throw new UserUnauthorizedException('You must be authorised to access this resource');
        }

        // if the user has any of the permissions
        if (is_array($permission)) {
            foreach ($permission as $perm) {
                if (in_array($perm, $this->request->getAuth()->permissions)) {
                    return true;
                }
            }
            throw new UserUnauthorizedException($message);
        }

        // if the user has the permission
        if (!in_array($permission, $this->request->getAuth()->permissions)) {
            throw new UserUnauthorizedException($message);
        }
        return true;
    }


    /**
     * Like CanAny but only check for one permission at a time
     *
     * @param string $permission The permission to check for
     * @param string|null $message The message to be returned if the user does not have the required permission
     *
     * @return bool Returns true if the user has the required permission
     * @throws UserUnauthenticatedException
     * @throws UserUnauthorizedException
     */
    public function can(string $permission, ?string $message = 'You do not have access to this resource'): bool
    {
        // check if the user is authenticated even before checking for permissions
        $this->mustAuthenticate();

        $message = $message ?? 'You do not have access to this resource';

        // if the user has no permissions at all
        if (is_null($this->request->getAuth()->permissions)) {
            throw new UserUnauthorizedException('You must be authorised to access this resource');
        }

        // if the user has the permission
        if (!in_array($permission, $this->request->getAuth()->permissions)) {
            throw new UserUnauthorizedException($message);
        }
        return true;
    }

    /**
     * Similar to canAny only that this checks if the user has all the passed permissions
     * @param array $permissions The permissions to check for
     * @param string|null $message The message to be returned if the user does not have the required permission
     * @return bool Returns true if the user has the required permission, else returns a BaseResponse object
     * @throws UserUnauthenticatedException If the user is not authenticated
     * @throws UserUnauthorizedException If the user does not have the required permission
     */
    public function canAll(array $permissions, ?string $message = 'You do not have access to this resource'): bool
    {
        // check if the user is authenticated even before checking for permissions
        $this->mustAuthenticate();

        $message = $message ?? 'You do not have access to this resource';

        // if the user has no permissions at all
        if (is_null($this->request->getAuth()->permissions)) {
            throw new UserUnauthorizedException('You must be authorised to access this resource');
        }

        // if the user has any of the permissions
        foreach ($permissions as $perm) {
            if (!in_array($perm, $this->request->getAuth()->permissions)) {
                throw new UserUnauthorizedException($message);
            }
        }
        return true;
    }


    /**
     * This method holds the currently logged in user object
     * @return ContextUserObject|null The currently logged in user object or null if no user is logged in
     */
    public function auth(): ?ContextUserObject
    {
        return $this->request->getAuth();
    }

    /**
     * This method ensures that only authenticated users can access a resource
     *
     * @param string|null $message Use this to override the default message
     * @return bool
     * @throws UserUnauthenticatedException
     */
    public function mustAuthenticate(?string $message = 'You must be authenticated to access this resource'): bool
    {
        if (is_null($this->auth()) || !$this->auth()->authenticated){
            throw new UserUnauthenticatedException($message);
        }
        return true;
    }

    /**
     * Checks if the auth extra data contains a key or not
     *
     * @param string $key
     * @return bool Returns true if the key is present in the authExtra data else returns false
     */
    public function authExtraHas(string $key): bool
    {
        if (!$this->auth()->authExtra){
            return false;
        }
        return array_key_exists($key, $this->auth()->authExtra);
    }


    /**
     * Returns the auth extra data by key
     *
     * @param string $key
     * @return mixed|null Returns the value of the key if it exists else returns null
     */
    public function getAuthExtraByKey(string $key): mixed
    {
        if ($this->authExtraHas($key)){
            return $this->auth()->authExtra[$key];
        }
        return null;
    }


}
