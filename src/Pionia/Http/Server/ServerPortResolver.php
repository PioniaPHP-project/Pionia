<?php

namespace Pionia\Http\Server;

/**
 * Shared HTTP listen port for `serve`, RoadRunner, and frontend API proxy targets.
 *
 * Precedence: CLI override → PORT / SERVER_PORT → [server] / [roadrunner] in settings.ini → default.
 */
final class ServerPortResolver
{
    public const DEFAULT_PORT = 8003;

    public function resolve(int|string|null|false $cliOverride = null): int
    {
        if (is_scalar($cliOverride) && $cliOverride !== '' && $cliOverride !== false) {
            return max(1, (int) $cliOverride);
        }

        if (!function_exists('env')) {
            return self::DEFAULT_PORT;
        }

        foreach (['PORT', 'SERVER_PORT', 'port', 'server_port'] as $key) {
            $value = env($key);
            if (is_scalar($value) && $value !== '') {
                return max(1, (int) $value);
            }
        }

        foreach (['server', 'roadrunner'] as $section) {
            $config = env($section);
            if (!is_array($config)) {
                continue;
            }

            foreach (['PORT', 'port'] as $key) {
                if (isset($config[$key]) && is_scalar($config[$key]) && $config[$key] !== '') {
                    return max(1, (int) $config[$key]);
                }
            }
        }

        return self::DEFAULT_PORT;
    }
}
