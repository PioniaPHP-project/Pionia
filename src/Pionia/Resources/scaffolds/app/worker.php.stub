<?php

/**
 * RoadRunner worker entry point.
 *
 * Started by the RoadRunner binary (.rr.yaml → server.command).
 * Boots Pionia once per worker process, then handles HTTP, jobs, or Centrifuge by RR_MODE.
 */

use Pionia\Http\Worker\PioniaWorker;
use Pionia\Realm\AppRealm;

require __DIR__ . '/vendor/autoload.php';

/** @var AppRealm $app */
$app = require __DIR__ . '/bootstrap/application.php';

$web = $app->make(AppRealm::WEB_APP_TAG);
(new PioniaWorker($web))->run();
