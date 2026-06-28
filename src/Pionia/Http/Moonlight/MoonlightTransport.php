<?php

namespace Pionia\Http\Moonlight;

enum MoonlightTransport: string
{
    case Http = 'http';
    case Job = 'job';
    case WebSocket = 'websocket';
}
