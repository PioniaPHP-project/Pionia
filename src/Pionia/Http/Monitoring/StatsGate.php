<?php

namespace Pionia\Http\Monitoring;

use Pionia\Http\Pages\HttpErrorPage;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;

/**
 * Optional gate for `/stats` and `/stats.json`.
 *
 * Enable via `[stats] ENABLED=true` / `STATS_ENABLED=true`, or implicitly when `DEBUG=true`.
 * When `TOKEN` / `STATS_TOKEN` is set, require `?token=` or `X-Stats-Token`.
 */
class StatsGate
{
    public static function isEnabled(): bool
    {
        return apiStatsEnabled();
    }

    public static function isAuthorized(Request $request): bool
    {
        return apiStatsAuthorized($request);
    }

    /**
     * Returns a denial response, or null when access is allowed.
     */
    public static function deny(Request $request, bool $htmlOnForbidden = false): ?Response
    {
        if (!self::isEnabled()) {
            return self::notFoundResponse($request, $htmlOnForbidden);
        }

        if (!self::isAuthorized($request)) {
            return self::unauthorizedResponse($request, $htmlOnForbidden);
        }

        return null;
    }

    private static function notFoundResponse(Request $request, bool $htmlOnForbidden = false): Response
    {
        if ($htmlOnForbidden || self::prefersHtml($request)) {
            return new Response(
                HttpErrorPage::htmlRich(404, '<p>Developer stats are not available.</p>'),
                404,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        return Response::fromEnvelope(
            response((int) env('NOT_FOUND_CODE', 404), 'Not found'),
            404,
        );
    }

    private static function unauthorizedResponse(Request $request, bool $htmlOnForbidden): Response
    {
        if ($htmlOnForbidden || self::prefersHtml($request)) {
            return new Response(
                HttpErrorPage::htmlRich(
                    401,
                    '<p>Stats access denied. Provide <code>?token=</code> or <code>X-Stats-Token</code>.</p>',
                ),
                401,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        return Response::fromEnvelope(
            response((int) env('UNAUTHENTICATED_CODE', 401), 'Stats access denied'),
            401,
        );
    }

    private static function prefersHtml(Request $request): bool
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
