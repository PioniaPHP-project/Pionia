<?php

namespace Pionia\Http\Moonlight;

/**
 * Serializable Moonlight job payload for async workers (RoadRunner Jobs).
 */
final readonly class MoonlightJobPayload
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $service,
        public string $action,
        public array $payload = [],
        public ?string $switch = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $payload = is_array($data['payload'] ?? null)
            ? $data['payload']
            : $data;

        unset($payload['service'], $payload['action'], $payload['switch']);

        return new self(
            service: (string) ($data['service'] ?? ''),
            action: (string) ($data['action'] ?? ''),
            payload: $payload,
            switch: isset($data['switch']) ? (string) $data['switch'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service' => $this->service,
            'action' => $this->action,
            'payload' => $this->payload,
            'switch' => $this->switch,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function requestBody(): array
    {
        return array_merge(
            ['service' => $this->service, 'action' => $this->action],
            $this->payload,
        );
    }
}
