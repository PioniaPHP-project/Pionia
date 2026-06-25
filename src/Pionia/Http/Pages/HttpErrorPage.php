<?php

namespace Pionia\Http\Pages;

use Pionia\Http\Request\Request;
use Pionia\Http\Response\Response;

class HttpErrorPage
{
    public static function respond(Request $request, int $code, string $message): Response
    {
        if (self::wantsJson($request)) {
            return Response::json(
                response($code, $message)->getPrettyResponse() ?? '',
                $code,
            );
        }

        return new Response(
            self::html($code, $message),
            $code,
            ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }

    public static function html(int $code, string $message): string
    {
        return self::htmlRich($code, htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    }

    /**
     * @param string $messageHtml Pre-escaped or trusted static HTML for the message body.
     */
    public static function htmlRich(int $code, string $messageHtml): string
    {
        $codeHtml = htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8');
        $themeInit = PioniaTheme::initScript();
        $themeAssets = PioniaTheme::stylesheetTags();
        $themeToggle = PioniaTheme::toggleButton('position-absolute top-0 end-0 m-3');
        $favicon = PioniaTheme::ASSET_PREFIX . '/favicon.ico';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$codeHtml}</title>
    {$themeInit}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    {$themeAssets}
</head>
<body class="pionia-error-page position-relative">
    {$themeToggle}
    <div class="pionia-error-card" role="alert">
        <img src="{$favicon}" alt="" style="width:90px;margin-bottom:20px;">
        <div class="pionia-error-code">{$codeHtml}</div>
        <div class="pionia-error-message">{$messageHtml}</div>
    </div>
</body>
</html>
HTML;
    }

    public static function wantsJson(Request $request): bool
    {
        if ($request->query->has('json')) {
            return true;
        }

        $path = $request->getPathInfo();
        $apiBase = rtrim((string) apiBase(), '/');

        if ($apiBase !== '' && (str_starts_with($path, $apiBase . '/') || $path === $apiBase)) {
            return true;
        }

        $accept = (string) $request->headers->get('Accept', '');

        if ($accept === '' || $accept === '*/*') {
            return false;
        }

        if (str_contains($accept, 'application/json') && !str_contains($accept, 'text/html')) {
            return true;
        }

        return false;
    }
}
