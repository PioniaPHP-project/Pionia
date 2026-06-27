<?php

namespace Pionia\Http\Worker;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\BinaryFileResponse;
use Pionia\Http\Response\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Converts between PSR-7 (RoadRunner) and Pionia HTTP messages.
 */
class Psr7Bridge
{
    public static function toPioniaRequest(ServerRequestInterface $psr): Request
    {
        $uri = $psr->getUri();
        $path = $uri->getPath() !== '' ? $uri->getPath() : '/';
        $query = $uri->getQuery();
        $requestUri = $path . ($query !== '' ? '?' . $query : '');

        $server = [
            'REQUEST_METHOD' => $psr->getMethod(),
            'REQUEST_URI' => $requestUri,
            'SERVER_NAME' => $uri->getHost() ?: 'localhost',
            'SERVER_PORT' => $uri->getPort() ?: ($uri->getScheme() === 'https' ? 443 : 80),
            'HTTPS' => $uri->getScheme() === 'https' ? 'on' : '',
            'QUERY_STRING' => $query,
        ];

        foreach ($psr->getHeaders() as $name => $values) {
            $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
            $server[$key] = implode(', ', $values);
        }

        if ($psr->hasHeader('Host')) {
            $server['HTTP_HOST'] = $psr->getHeaderLine('Host');
        }

        $queryParams = $psr->getQueryParams();
        if ($queryParams === [] && $query !== '') {
            parse_str($query, $queryParams);
        }

        $cookies = $psr->getCookieParams();
        if ($cookies === [] && $psr->hasHeader('Cookie')) {
            $cookies = self::parseCookies($psr->getHeaderLine('Cookie'));
        }

        return Request::create(
            $requestUri,
            $psr->getMethod(),
            $queryParams,
            $cookies,
            self::uploadedFiles($psr),
            $server,
            (string) $psr->getBody(),
        );
    }

    public static function toPsr7Response(Response $response): ResponseInterface
    {
        $response->prepare(Request::create('/'));

        $headers = [];
        foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            $headers[$name] = $values;
        }

        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $psr = $factory->createResponse($response->getStatusCode());

        foreach ($headers as $name => $values) {
            foreach ($values as $value) {
                $psr = $psr->withAddedHeader($name, $value);
            }
        }

        if ($response instanceof BinaryFileResponse) {
            $stream = $factory->createStreamFromFile($response->getFile()->getPathname(), 'r');

            return $psr->withBody($stream);
        }

        $content = $response->getContent();
        $body = $factory->createStream($content === false ? '' : (string) $content);

        return $psr->withBody($body);
    }

    /**
     * @return array<string, string>
     */
    private static function parseCookies(string $header): array
    {
        $cookies = [];
        foreach (explode(';', $header) as $part) {
            $part = trim($part);
            if ($part === '' || !str_contains($part, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $part, 2);
            $cookies[urldecode(trim($name))] = urldecode(trim($value));
        }

        return $cookies;
    }

    /**
     * @return array<string, mixed>
     */
    private static function uploadedFiles(ServerRequestInterface $psr): array
    {
        $files = [];
        foreach ($psr->getUploadedFiles() as $name => $upload) {
            if (is_array($upload)) {
                continue;
            }

            if ($upload->getError() !== UPLOAD_ERR_OK) {
                continue;
            }

            $stream = $upload->getStream();
            $tmp = tempnam(sys_get_temp_dir(), 'pionia_upload_');
            if ($tmp === false) {
                continue;
            }

            file_put_contents($tmp, $stream->getContents());
            $files[$name] = [
                'name' => $upload->getClientFilename() ?? $name,
                'type' => $upload->getClientMediaType() ?? 'application/octet-stream',
                'tmp_name' => $tmp,
                'error' => UPLOAD_ERR_OK,
                'size' => $upload->getSize() ?? filesize($tmp),
            ];
        }

        return $files;
    }
}
