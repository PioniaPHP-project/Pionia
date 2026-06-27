<?php

namespace Pionia\TestSuite;

use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Response\BinaryFileResponse;
use Pionia\Http\Response\Response;

class TestResponse
{
    public function __construct(
        private readonly Response | BinaryFileResponse $response,
    ) {
    }

    public function base(): Response | BinaryFileResponse
    {
        return $this->response;
    }

    public function status(): int
    {
        return $this->response->getStatusCode();
    }

    public function content(): string
    {
        return (string) $this->response->getContent();
    }

    /**
     * @return array<string, mixed>
     */
    public function json(bool $associative = true): array
    {
        return json_decode($this->content(), $associative, 512, JSON_THROW_ON_ERROR);
    }

    public function pioniaPayload(): array
    {
        return $this->json();
    }

    public function header(string $name): ?string
    {
        return $this->response->headers->get($name);
    }

    public function toBaseResponse(): ?BaseResponse
    {
        if ($this->response instanceof Response) {
            return $this->response;
        }

        return null;
    }
}
