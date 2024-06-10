<?php

namespace Pionia\core;

/**
 * This is the base class for the framework
 *
 * It holds the settings and the version of the framework
 *
 * All classes in the framework should extend this class to have access to the core configurations
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class Pionia
{

    public static array | null $settings = null;

    public static string $version = '1.0.7';

    public static string $name = 'Pionia';

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

    protected static function getServerSettings(): array
    {
        $settings =  self::getSetting("server");
        if (is_array($settings)) {
            return $settings;
        }
        return [];
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
