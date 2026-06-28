<?php

namespace Pionia\Http\Moonlight;

/**
 * Transport metadata for Moonlight dispatch (HTTP, job queue, WebSocket).
 */
final readonly class MoonlightContext
{
    public function __construct(
        public MoonlightTransport $transport = MoonlightTransport::Http,
        public ?string $connectionId = null,
        public ?string $switch = null,
    ) {
    }

    public static function http(?string $switch = null): self
    {
        return new self(MoonlightTransport::Http, switch: $switch);
    }

    public static function job(?string $switch = null): self
    {
        return new self(MoonlightTransport::Job, switch: $switch);
    }

    public static function websocket(?string $connectionId = null, ?string $switch = null): self
    {
        return new self(MoonlightTransport::WebSocket, $connectionId, $switch);
    }
}
