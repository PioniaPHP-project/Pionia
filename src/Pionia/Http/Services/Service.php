<?php

namespace Pionia\Pionia\Http\Services;


use Pionia\Pionia\Cache\Cacheable;

/**
 * Base Service for generic services
 */
class Service extends BaseService {
    use Cacheable;
}
