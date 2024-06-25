<?php

namespace Pionia\Exceptions;

/**
 * This exception is thrown when a limit is provided in a paginated query using the Pagination class
 *
 * To ovoid this exception, call the limitBy(limit_here) method on the Pagination object
 */
class LimitPaginationException extends BaseException {}
