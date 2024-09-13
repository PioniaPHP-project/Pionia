<?php

namespace Pionia\Auth;

/**
 * This is the context object holding the current session
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class ContextUserObject
{
    /**
     * You can store here your user object
     * @var object
     */
    public object $user;
    /**
     * Turn this to true if the user is authenticated
     * @var bool
     */
    public bool $authenticated = false;
    /**
     * Set this to the user's object
     * @var array|null
     */
    public array|null $permissions;

    /**
     * this holds any other data about the logged-in session holder, can be user to hold user domain, user role etc
     * @var array|null
     */
    public array | null $authExtra = null;
}
