<?php

namespace Pionia\Exceptions;

/**
 * This exception is thrown when an offset is passed in a paginated query using the Pagination class
 *
 * To ovoid this exception, call the startFrom(offset_here) method on the Pagination object
 */
class OffsetPaginationException extends BaseException {}
