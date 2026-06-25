<?php

namespace Pionia\Http\Pages;

class MaintenancePage
{
    public static function html(?string $message = null): string
    {
        $appName = htmlspecialchars((string) realm()->getAppName(), ENT_QUOTES, 'UTF-8');
        $messageHtml = htmlspecialchars($message ?? maintenanceMessage(), ENT_QUOTES, 'UTF-8');
        $themeInit = PioniaTheme::initScript();
        $themeAssets = PioniaTheme::stylesheetTags();
        $themeToggle = PioniaTheme::toggleButton('position-absolute top-0 end-0 m-3');
        $favicon = PioniaTheme::ASSET_PREFIX . '/favicon.ico';
        $welcomeCss = FrameworkWelcomePage::ASSET_PREFIX . '/welcome.css';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>{$appName} · Maintenance</title>
    {$themeInit}
    <link rel="icon" href="{$favicon}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{$welcomeCss}">
    {$themeAssets}
</head>
<body class="pionia-error-page position-relative">
    {$themeToggle}
    <div class="pionia-error-card" role="alert">
        <img src="{$favicon}" alt="" style="width:90px;margin-bottom:20px;">
        <div class="pionia-error-code" style="font-size:3rem;line-height:1.1;">
            <i class="bi bi-tools" aria-hidden="true"></i>
        </div>
        <h1 class="h3 mb-2">We&rsquo;ll be right back</h1>
        <div class="pionia-error-message">{$messageHtml}</div>
        <p class="text-muted small mt-3 mb-0">{$appName} is temporarily unavailable while we perform maintenance.</p>
    </div>
</body>
</html>
HTML;
    }
}
