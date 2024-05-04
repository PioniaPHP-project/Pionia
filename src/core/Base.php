<?php

namespace jetPhp\core;

class Base
{

    public static array | null $settings = null;

    public static string $version = '1.0.0';

    public static string $name = 'JetPhp';

    public function __construct()
    {
        $this::resolveSettingsFromIni();
    }


    public static function getSettings(): array | null
    {
        return self::$settings;
    }

    public static function getSetting(string $key): mixed
    {
        return self::$settings[$key] ?? null;
    }

    public static function getSettingOrDefault(string $key, mixed $default): mixed
    {
        return self::$settings[$key] ?? $default;
    }

    public static function resolveSettingsFromIni(): mixed
    {
        if (defined('SETTINGS') === false){
            return null;
        }
        if (defined('SETTINGS')){
            self::$settings = parse_ini_file(SETTINGS, true);
        }


        if (defined('SESSION') === true && session_status() === PHP_SESSION_ACTIVE){
            self::$settings = array_merge(self::$settings, $_SESSION);
        }

        if ($_SERVER){
            self::$settings = array_merge(self::$settings, $_SERVER);
        }

        if ($_ENV){
            self::$settings = array_merge(self::$settings, $_ENV);
        }

        return self::$settings;
    }
}
