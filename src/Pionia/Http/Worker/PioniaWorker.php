<?php

namespace Pionia\Http\Worker;

use Pionia\Base\WebApplication;
use Pionia\Http\Moonlight\MoonlightCentrifugeHandler;
use Pionia\Http\Moonlight\MoonlightJobConsumer;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\Environment\Mode;

/**
 * Unified RoadRunner worker — dispatches by RR_MODE (http, jobs, centrifuge).
 */
final class PioniaWorker
{
    public function __construct(
        private readonly WebApplication $app,
    ) {
    }

    public function run(): void
    {
        $mode = Environment::fromGlobals()->getMode();

        match ($mode) {
            Mode::MODE_JOBS => (new MoonlightJobConsumer($this->app))->run(),
            Mode::MODE_CENTRIFUGE => MoonlightCentrifugeHandler::run($this->app),
            default => (new RoadRunnerWorker($this->app))->run(),
        };
    }
}
