<?php

namespace Pionia\Pionia\Interceptors\Authentications;

use Pionia\Pionia\Base\PioniaApplication;
use Pionia\Pionia\Base\Utils\Containable;
use Pionia\Pionia\Base\Utils\Microable;
use Pionia\Pionia\Utilities\Arrayable;

class AuthenticationChain
{
    use Microable, Containable;

    private Arrayable $authentications;

    public function __construct(PioniaApplication $container)
    {
        $this->context = $container->context;
        $this->authentications = $this->getOrDefault('authentications', Arrayable::toArrayable([]));
    }
}
