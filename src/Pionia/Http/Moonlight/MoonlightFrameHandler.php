<?php

namespace Pionia\Http\Moonlight;

use Pionia\Http\Response\ApiResponse;

/**
 * Parses Moonlight JSON frames (WebSocket RPC, Centrifugo) into service/action dispatch.
 */
final class MoonlightFrameHandler
{
    /**
     * @param array<string, mixed> $frame
     *
     * @return array<string, mixed>
     */
    public static function handle(array $frame, ?string $switch = null): array
    {
        $service = (string) ($frame['service'] ?? '');
        $action = (string) ($frame['action'] ?? '');

        if ($service === '' || $action === '') {
            return self::envelopeArray(response(400, 'Missing service or action in frame'));
        }

        $params = is_array($frame['data'] ?? null) ? $frame['data'] : $frame;
        unset($params['service'], $params['action'], $params['data']);

        $response = MoonlightDispatcher::dispatchPayload(
            array_merge(['service' => $service, 'action' => $action], $params),
            MoonlightRegistry::callable($switch),
        );

        return self::envelopeArray($response);
    }

    /**
     * @return array<string, mixed>
     */
    private static function envelopeArray(ApiResponse $response): array
    {
        $json = $response->getPrettyResponse();

        if (!is_string($json) || $json === '') {
            return [
                'returnCode' => 500,
                'returnMessage' => 'Empty Moonlight response',
                'returnData' => null,
            ];
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
