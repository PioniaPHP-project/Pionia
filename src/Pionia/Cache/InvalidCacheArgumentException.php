<?php

namespace Pionia\Cache;

use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentException;

final class InvalidCacheArgumentException extends \InvalidArgumentException implements SimpleCacheInvalidArgumentException
{
}
