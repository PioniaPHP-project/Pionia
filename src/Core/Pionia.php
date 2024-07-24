<?php

namespace Pionia\Core;


/**
 * This is the base class for the framework
 *
 * It holds the settings and the version of the framework
 *
 * All classes in the framework should extend this class to have access to the core configurations
 *
 * @since 1.1.1 - Added the ability to change the name of the framework from the core :)
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class Pionia
{
    public static array | null $settings = null;

    public static string $version = '1.1.5';

    public static string $name = 'Pionia';

    public static function boot(): Pionia
    {
        return new Pionia();
    }

    public function __construct()
    {
        $this::resolveSettingsFromIni();

        $server = self::getServerSettings();

        if ($server && array_key_exists('APP_NAME', $server)){
            self::$name = $server['APP_NAME'];
        }
    }


    public static function getSettings(): array | null
    {
        return self::$settings;
    }

    /**
     * Checks for the setting and returns it
     * If the setting is not found, it returns null
     *
     * Here `SERVER` and `server` are the same
     * @param string $key
     * @return mixed
     */
    public static function getSetting(string $key): mixed
    {
        // check for small case first
        $key = strtolower($key);
        if (isset(self::$settings[$key])){
            return self::$settings[$key];
        }
        // check for uppercase last
        $key = strtoupper($key);
        return self::$settings[$key] ?? null;
    }

    public static function getServerSettings(): array
    {
        $settings =  self::getSetting("server");
        if (is_array($settings)) {
            return $settings;
        }
        return [];
    }

    public static function getSettingOrDefault(string $key, mixed $default): mixed
    {
        return self::getSetting($key) ?? $default;
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

    public static function getUploadSettings(): array
    {
        return self::getSetting('uploads') ?? [];
    }
}
