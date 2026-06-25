<?php

namespace Pionia\Http\Pages;

/**
 * Shared light/dark theme for framework HTML pages (welcome, stats, errors, maintenance).
 */
class PioniaTheme
{
    public const STORAGE_KEY = 'pionia-theme';

    public const ASSET_PREFIX = FrameworkWelcomePage::ASSET_PREFIX;

    public static function initScript(): string
    {
        $key = self::STORAGE_KEY;

        return <<<HTML
<script>
(function () {
    var key = '{$key}';
    var stored = localStorage.getItem(key);
    var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    document.documentElement.setAttribute('data-theme', theme);
    window.__pioniaTheme = theme;
})();
</script>
HTML;
    }

    public static function stylesheetTags(): string
    {
        $themeCss = self::ASSET_PREFIX . '/pionia-theme.css';
        $themeJs = self::ASSET_PREFIX . '/pionia-theme.js';

        return <<<HTML
<link rel="stylesheet" href="{$themeCss}">
<script defer src="{$themeJs}"></script>
HTML;
    }

    public static function toggleButton(string $extraClass = ''): string
    {
        $class = trim('pionia-theme-toggle btn btn-sm btn-pionia-outline ' . $extraClass);

        return <<<HTML
<button type="button" class="{$class}" data-pionia-theme-toggle aria-label="Toggle dark mode">
    <i class="bi bi-moon-stars-fill pionia-theme-icon-dark" aria-hidden="true"></i>
    <i class="bi bi-sun-fill pionia-theme-icon-light" aria-hidden="true"></i>
    <span class="pionia-theme-toggle-label">Theme</span>
</button>
HTML;
    }
}
