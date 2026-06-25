<?php
require __DIR__ . '/../../vendor/autoload.php';

use Pionia\Realm\AppRealm;

static $application = null;

if ($application === null) {
    $application = AppRealm::create(__DIR__);
}

return $application;
