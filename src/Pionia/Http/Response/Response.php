<?php

namespace Pionia\Http\Response;

use Pionia\Http\Bag\HeaderBag;
use Pionia\Http\Request\Request;

/**
 * Native HTTP response for Pionia (no Symfony dependency).
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class Response
{
    public readonly HeaderBag $headers;

    public const JSON_HEADERS = ['Content-Type' => 'application/json; charset=UTF-8'];

    public function __construct(
        private string $content = '',
        private int $statusCode = 200,
        array $headers = [],
    ) {
        $this->headers = new HeaderBag($headers);
    }

    public static function json(string $content, int $status = 200, array $headers = []): self
    {
        return new self($content, $status, array_merge(self::JSON_HEADERS, $headers));
    }

    public static function fromEnvelope(BaseResponse $envelope, int $status = 200, array $headers = []): self
    {
        return self::json($envelope->getPrettyResponse() ?? '', $status, $headers);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content ?? '';

        return $this;
    }

    public function prepare(Request $request): static
    {
        if (!$this->headers->has('Content-Type') && $this->content !== '') {
            $this->headers->set('Content-Type', 'text/html; charset=UTF-8');
        }

        if ($this->content !== '' && !$this->headers->has('Content-Length')) {
            $this->headers->set('Content-Length', (string) strlen($this->content));
        }

        return $this;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers->all() as $name => $values) {
            foreach ($values as $value) {
                header($name . ': ' . $value, false);
            }
        }

        echo $this->getContent();
    }
}
