<?php

namespace Pionia\TestSuite\Concerns;

use Pionia\Base\WebApplication;
use Pionia\Realm\AppRealm;

/**
 * Boots the example application via tests/bootstrap.php (BASE_PATH → example/).
 */
trait CreatesApplication
{
    protected function application(): AppRealm
    {
        return app();
    }

    protected function webApplication(): WebApplication
    {
        return $this->application()->make(AppRealm::WEB_APP_TAG);
    }
}
