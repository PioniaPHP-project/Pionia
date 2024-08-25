<?php

namespace Pionia\Pionia\Exceptions;

use Pionia\Exceptions\BaseException;

/**
 * This exception is thrown when one tries to access a protected resource without being authenticated
 */
class UserUnauthenticatedException extends BaseException {}
