<?php

namespace Pionia\Http;

use Pionia\Http\Pages\HttpErrorPage;
use Pionia\Http\Pages\MaintenancePage;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;

/**
 * Application-wide maintenance gate (503) for HTTP requests.
 *
 * Enable via `[maintenance] ENABLED=true` / `MAINTENANCE_MODE=true`.
 * Optional `BYPASS_TOKEN` accepts `X-Maintenance-Bypass` or `?bypass=`.
 */
class MaintenanceMode
{
    public static function respond(Request $request): ?Response
    {
        if (!maintenanceModeEnabled() || self::shouldBypass($request)) {
            return null;
        }

        $code = (int) env('MAINTENANCE_CODE', 503);
        $message = maintenanceMessage();
        $headers = ['Content-Type' => HttpErrorPage::wantsJson($request) ? 'application/json' : 'text/html; charset=UTF-8'];

        $retryAfter = maintenanceRetryAfter();
        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) $retryAfter;
        }

        if (HttpErrorPage::wantsJson($request)) {
            return Response::json(
                response($code, $message)->getPrettyResponse() ?? '',
                $code,
                $headers,
            );
        }

        return new Response(MaintenancePage::html($message), $code, $headers);
    }

    private static function shouldBypass(Request $request): bool
    {
        if (str_starts_with($request->getPathInfo(), '/__pionia/')) {
            return true;
        }

        return maintenanceBypassAuthorized($request);
    }
}
