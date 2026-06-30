<?php

namespace Pionia\Http\Background;

/**
 * Returned when a Moonlight job was accepted by the RoadRunner Jobs pipeline.
 */
final readonly class JobSubmission
{
    public function __construct(
        public string $jobId,
        public bool $queued = true,
    ) {
    }
}
