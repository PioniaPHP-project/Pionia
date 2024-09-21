<?php

namespace Pionia\Http\Services;


use Pionia\Cache\Cacheable;

/**
 * Base Service for generic services
 */
class Service extends BaseService {
    use Cacheable;
}
