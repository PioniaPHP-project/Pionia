<?php

/**
 * RoadRunner worker entry point.
 *
 * Started by the RoadRunner binary (.rr.yaml → server.command).
 * Boots Pionia once per worker process, then handles PSR-7 requests in a loop.
 */

use Pionia\Http\Worker\RoadRunnerWorker;
use Pionia\Realm\AppRealm;

require __DIR__ . '/../vendor/autoload.php';

/** @var AppRealm $app */
$app = require __DIR__ . '/bootstrap/routes.php';

$web = $app->make(AppRealm::WEB_APP_TAG);
(new RoadRunnerWorker($web))->run();
