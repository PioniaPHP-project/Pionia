<?php

namespace Pionia\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Pionia\Core\Pionia;
use Monolog\Handler\StreamHandler; // The StreamHandler sends log messages to a file on your disk
use Monolog\Logger; // The Logger class is the main class of the Monolog library
/**
 */
class PioniaLogger
{
    private static array $hiddenKeys = ['password', 'pass', 'pin', 'passwd', 'secret_key', 'pwd', 'token', 'credit_card', 'creditcard', 'cc', 'secret', 'cvv', 'cvn'];

    /**
     * Call this method to initialise the logger.
     *
     * To turn off debug logging, turn off DEBUG in database.ini.
     * You can turn off DEBUG but still want to maintain logging alone, there you leave LOG_REQUESTS on in the database.ini.
     *
     * Also you can define your own LOG_DESTINATION destination. This is where you want to log to. default is stdout, but it can be file. If
     * it is a file, a clear file path should be provided. It will be created if not already available.
     *
     * You can also define the LOG_FORMAT. This can be either TEXT or JSON. Default is TEXT.
     *
     * You can also define the LOGGED_SETTINGS. This is a comma separated list of settings that you want to log along to the log file.
     *
     * You can also define the HIDE_IN_LOGS. This is a comma separated list of settings keys that you want to hide in the logs.
     * Default list comprises of `password`, `pass`, `passwd`, `pwd`, `token`, `credit_card`, `creditcard`, `cc`, `secret`, `cvv`, `cvn`.
     * Nothing gets removed from list, but whatever you add to the HIDE_IN_LOGS will be added and hidden in the logs.
     *
     * You can also define the HIDE_SUB. This is the string that will replace the hidden values in the logs. Default is `*********`.
     *
     * If you log to a file, then you watch the file using `tail -f /path/to/file.log` to see the logs in real time.
     *
     * @return Logger|null
     */
    public static function init(): Logger | null
    {
        $settings = pionia::getSettings();
        $serverSettings = pionia::getServerSettings();
        $processors = $serverSettings['LOG_PROCESSORS'] ?? [];

        if (array_key_exists("DEBUG", $serverSettings)) {
            $debug = $serverSettings["DEBUG"];
        } else {
            $debug = true;
        }

        if (array_key_exists("LOG_REQUESTS", $serverSettings)) {
            $logRequests = $serverSettings["LOG_REQUESTS"];
        } else {
            $logRequests = true;
        }

        if (!$debug && !$logRequests) {
            return null;
        }

        if (array_key_exists("LOG_DESTINATION", $serverSettings)) {
            $destination = $serverSettings["LOG_DESTINATION"];
        } else {
            $destination = 'stdout';
        }

        if ($destination === 'stdout') {
            $stream = "php://stdout";
        } else {
            $stream = $destination;
        }

        $logger = new Logger(pionia::$name);

        if (is_string($processors)) {
            $processors = explode(',', $processors);
        }

        // add the processors the developer has registered in the database.ini file
        if (is_array($processors) && !empty($processors)) {
            foreach ($processors as $processor) {
                if (!empty($processor)) {
                    $processor= trim($processor);
                    $logger->pushProcessor(new $processor());
                }
            }
        }

        $logger->pushProcessor(function ($record) use ($debug, $serverSettings, $settings){
            // here the developer can also log some parts of the database.ini file
            if (isset($serverSettings['LOGGED_SETTINGS'])){
                $settings_ = explode(',', $serverSettings['LOGGED_SETTINGS']);
                if (is_array($settings_) && !empty($settings_)) {
                    $all = [];
                    foreach ($settings_ as $key) {
                        if (!empty($settings[$key])) {
                            $current = $settings[$key];
                            if (is_array($current)) {
                                $cleaned = self::hideInLogs($current);
                                $all = array_merge($all, $cleaned);
                            }
                        }
                    }
                    $record->extra = array_merge($record->extra, $all);
                }
            }
            return $record;
        });

        $outFormat = 'TEXT';

        if (array_key_exists('LOG_FORMAT', $serverSettings)) {
            $outFormat = strtoupper($serverSettings['LOG_FORMAT']);
        }

        if ($outFormat === 'JSON') {
            $formatter = new JsonFormatter(1, true, true, true);
        } else {
            $output = '[%datetime%] '.strtolower(pionia::$name).".%level_name% >> %message% :: %context% %extra%\n";
            $formatter = new LineFormatter($output, null, true, true);
        }

        if ($logRequests || $debug) {
            $stream_handler = new StreamHandler($stream, Level::Debug);
            $stream_handler->setFormatter($formatter);
            $logger->pushHandler($stream_handler);
        }
        return $logger;
    }

    /**
     * This method will hide the secure keys in the logs
     * @param array $data The data whose secure keys are to be hidden
     * @return array The data with the hidden keys hidden
     */
    public static function hideInLogs(mixed $data = []): array
    {
        if (!is_array($data)) {
            return [];
        }
        // this method will hide the secured keys in the logs
        $keys = self::$hiddenKeys;
        if (!empty($serverSettings['HIDE_IN_LOGS'])) {
            $keys = array_merge($keys, explode(',', $serverSettings['HIDE_IN_LOGS']));
        }
        $sub = $serverSettings['HIDE_SUB'] ?? '*********';

        array_walk_recursive($data, function (&$value, $key) use ($keys, $sub) {
            if (in_array($key, $keys)) {
                $value = $sub;
            }
        });

        return $data;
    }

}
