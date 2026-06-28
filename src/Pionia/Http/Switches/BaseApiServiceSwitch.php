<?php

namespace Pionia\Http\Switches;

use Exception;
use Pionia\Contracts\BaseSwitchContract;
use Pionia\Documentation\DocsGate;
use Pionia\Http\Moonlight\MoonlightDispatcher;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Response\Response;
use Throwable;

/**
 * This is the base class for the APIRoute service switch. It is used to switch between different services based on the request data.
 *
 * The child class must implement the registerServices method to return an array of services.
 * It requires the request to define the `SERVICE` key in the request data and the `ACTION` key to define the action to be performed.
 *
 * The SERVICE is the class that will be called when the SERVICE_NAME is called.
 * The ACTION is the method that will be called on the SERVICE.
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 * @see BaseResponse for the response returned by this swicher's processServices method
 *
 */
abstract class BaseApiServiceSwitch implements BaseSwitchContract
{
    /**
     * This method checks the request data for the `SERVICE` key and processes the service based on it
     *
     * @param Request $request The request object
     * @return BaseResponse The response object
     * @throws Throwable
     */
    private static function processServices(Request $request): BaseResponse
    {
        $controller = get_called_class() . '::processor';

        return MoonlightDispatcher::dispatch($request, fn () => services($controller));
    }

    /**
     * This is the sole action to be called in the routes file. It processes the request and returns the response
     * @param Request $request
     * @throws Throwable
     */
    public static function processor(Request $request): Response
    {
        try {
            $response = self::processServices($request);
        } catch (Throwable $e) {
            $response = pionia_handle_exception($e, $request);
        }

        if (shouldLogResponses()) {
            logger()->debug($response->getPrettyResponse());
        }

        return Response::fromEnvelope($response);
    }

    /**
     * GET on the version root — API overview or redirect to interactive docs.
     */
    public static function overview(Request $request): Response
    {
        $version = (string) ($request->attributes->get('apiVersion') ?? defaultApiVersion());

        if (self::prefersHtmlResponse($request)) {
            if (apiDocsEnabled()) {
                $location = '/docs';
                $token = $request->query->get('token');
                if (is_string($token) && $token !== '') {
                    $location .= '?token=' . rawurlencode($token);
                }

                return new Response('', 302, ['Location' => $location]);
            }

            return new Response(
                '<!DOCTYPE html><html><body style="font-family:system-ui,sans-serif;padding:2rem;">'
                . '<h1>Moonlight API</h1>'
                . '<p>Send <code>POST</code> JSON <code>{ "service", "action", ...params }</code> to this path.</p>'
                . '<p>Health: <a href="' . htmlspecialchars(apiPingPath($version), ENT_QUOTES) . '">' . htmlspecialchars(apiPingPath($version), ENT_QUOTES) . '</a></p>'
                . '</body></html>',
                200,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        return Response::fromEnvelope(response(0, 'Moonlight API', [
            'version' => $version,
            'dispatch' => [
                'method' => 'POST',
                'path' => rtrim(apiVersionPath($version), '/'),
                'body' => ['service' => '<alias>', 'action' => '<action>'],
            ],
            'ping' => apiPingPath($version),
            'docs' => apiDocsEnabled() ? '/docs' : null,
        ]));
    }

    /**
     * This is just for checking the api status
     *
     * You can even override it in your own switch class
     */
    public static function ping(Request $request): Response
    {
        $data = [
            'app' => app()->getAppName(),
            'pv'=> app()->phpVersion(),
            'fv'=> app()->appVersion,
            'port' => $request->getPort(),
            'uri' => $request->getRequestUri(),
            'schema' => $request->getScheme(),
        ];

        $response = response(0, 'pong', isDebug() ? $data : null);

        if (shouldLogResponses()) {
            logger()->info($response->getPrettyResponse());
        }

        return Response::fromEnvelope($response);
    }

    /**
     * Debug-only Moonlight action catalog (same data as `pionia api:catalog`).
     */
    public static function catalog(Request $request): Response
    {
        if ($denied = DocsGate::deny($request)) {
            return $denied;
        }

        $collector = new \Pionia\Documentation\MoonlightDocCollector();
        $exporter = new \Pionia\Documentation\MoonlightCatalogExporter();
        $catalog = $collector->collect();
        $payload = $exporter->toArray($catalog);
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if (self::prefersHtmlResponse($request)) {
            return new Response('', 302, ['Location' => '/docs']);
        }

        return Response::json($json);
    }

    private static function prefersHtmlResponse(Request $request): bool
    {
        $accept = (string) $request->headers->get('Accept', '');

        if ($accept === '' || $accept === '*/*') {
            return false;
        }

        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return false;
        }

        return str_contains($accept, 'text/html');
    }
}
