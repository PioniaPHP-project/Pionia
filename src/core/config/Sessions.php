<?php

namespace jetPhp\core\config;

/**
 * This class is responsible for handling all session related operations
 */
class Sessions
{
    /**
     * Destroys all data registered to a session
     *
     * @return bool true on success or false on failure.
     */
    public static function destroy()
    {
        return session_destroy();
    }

    /**
     * Initialize session data
     * @param $options – [optional]
     * If provided, this is an associative array of options that will override the currently set
     * session configuration directives. The keys should not include the session. prefix.
     * In addition to the normal set of configuration directives,va read_and_close option may also be provided.
     * If set to TRUE,this will result in the session being closed immediately after being read,
     * thereby avoiding unnecessary locking if the session data won't be changed.
     * @return bool
     */
    public static function start($options = null){
        return session_start($options);
    }

    /**
     * Sets the given key to the given value into the session and returns the current entire session values
     * @param $key
     * @param $value
     * @return array
     */
    public static function set($key, $value){
        $_SESSION[$key] = $value;
        return $_SESSION;
    }

    /**
     * Gets and returns a certain value from the session
     * @param $key
     * @return mixed
     */
    public static function get($key){
        return $_SESSION[$key];
    }

    /**
     * Checks if a certain key is already in the session
     * @param $key
     * @return bool
     */
    public static function has($key){
        return isset($_SESSION[$key]);
    }

    /**
     * Destroys a specific key in the session
     * @param $key
     * @return bool
     */
    public static function remove($key)
    {
        unset($_SESSION[$key]);
        return true;
    }

    /**
     * Update the current session id with a newly generated one
     * @param $should_delete_old_session
     * @return bool
     */
    public static function regerateKey($should_delete_old_session = true){
        return session_regenerate_id($should_delete_old_session);
    }

    /**
     * Set the session cookie parameters
     * @param $lifetime_or_option –  An associative array which may have any of the keys lifetime, path, domain, secure, httponly and samesite.
     * The values have the same meaning as described for the parameters with the same name.
     * The value of the samesite element should be either Lax or Strict.
     * If any of the allowed options are not given, their default values are the same as the default values of the explicit parameters.
     * If the samesite element is omitted, no SameSite cookie attribute is set
     * @return bool
     */
    public static function configure($lifetime_or_option = [])
    {
        return session_set_cookie_params($lifetime_or_option);
    }
}
