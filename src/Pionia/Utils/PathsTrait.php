<?php

namespace Pionia\Pionia\Utils;


trait PathsTrait
{
    /**
     * The directory where the frontend assets are stored.
     * @param $path
     * @return string
     */
    public function publicPath($path = null): string
    {
        return $this->appRoot('public/' . ($path ?? ''));
    }

    /**
     * The path to the resources directory.
     */
    public function mediaPath(): string
    {
        return $this->appRoot('media');
    }

    /**
     * The root folder of the application.
     * @param string|null $path
     * @param int $levels
     * @return string
     */
    public function appRoot(?string $path = null, $levels = 3): string
    {
        if (defined("BASEPATH")){
            return BASEPATH.($path ? DIRECTORY_SEPARATOR.$path : '');
        }
        return dirname(__DIR__, $levels).($path ? DIRECTORY_SEPARATOR.$path : '');
    }

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function phpPath(): string
    {
        return PHP_BINARY;
    }

    public function envPath(?string $path = null): string
    {
        return $path ? $this->appRoot('Environment').DIRECTORY_SEPARATOR.$path : $this->appRoot('Environment');
    }
}