<?php

namespace Pionia\Core\Interceptions;

use Pionia\Core\Helpers\ContextUserObject;
use Pionia\Request\Request;

/**
 * This is an interception class that will run against all requests. It is what the developer should
 * extend to create their own way to authenticate users.
 *
 * Once any of the authentication backends set the user object to context, the authentication process will be terminated
 * and that user object will be used instead. This is useful for cases where you have multiple authentication backends
 * for example, you can have some requests that are authenticated via JWT and others via session.
 */


/**
 * This is the base class for all authentication backends.
 *
 * All authentication backends must extend this class and implement the authenticate method.
 *
 * The authenticate method should return a ContextUserObject object.
 *
 * The ContextUserObject object should have the following properties:
 * - user: object - The user object
 * - authenticated: bool - True if the user is authenticated, false otherwise
 * - permissions: array|null - The user's permissions
 * - authExtra: array|null - Any other data about the logged-in session holder, can be used to hold user domain, user role etc
 *
 * @example
 * ```php
 * use Pionia\Core\Interceptions\BaseAuthenticationBackend;
 * class MyAuthenticationBackend extends BaseAuthenticationBackend
 * {
 *   // This is not something you should do in your project, but just gives you the best idea of how to implement the authenticate method
 *    public function authenticate(Request $request): ContextUserObject
 *   {
 *      // your authentication logic here
 *      $data = $request->getData();
 *      $username = $data['username'];
 *      // get the user object from the database
 *      $builder = new QueryBuilder();
 *      $user = $builder->one('select * from users where username = :username LIMIT 1', ['username' => $username]);
 *      if ($user) {
 *          $contextUserObject = new ContextUserObject();
 *          $contextUserObject->user = $user;
 *          $contextUserObject->authenticated = true;
 *          $contextUserObject->permissions = ['read', 'write'];
 *          $contextUserObject->authExtra = ['role' => user['role']];
 *          return $contextUserObject;
 *      }
 *  }
 * }
 * ```
 *
 * @property Request $request - The request object
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
abstract class BaseAuthenticationBackend
{
    /**
     * @param Request $request
     * @return ContextUserObject|null
     */
    public abstract function authenticate(Request $request): ?ContextUserObject;
}
