<?php

namespace Pionia\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Processor\GitProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Pionia\core\Pionia;

use Monolog\Handler\StreamHandler; // The StreamHandler sends log messages to a file on your disk
use Monolog\Logger;
use Pionia\exceptions\BaseException;

// The Logger instance

/**
 */
class PioniaLogger extends Pionia
{
    public function __construct()
    {
        parent::__construct();
    }


    /**
     * Call this method to initialise the logger.
     *
     * To turn off debug logging, turn off DEBUG in settings.ini.
     * You can turn off DEBUG but still want to maintain logging alone, there you leave LOG_REQUESTS on in the settings.ini.
     *
     * Also you can define your own LOG_DESTINATION destination. This is where you want to log to. default is terminal, but it can a file. If
     * it is a file, a clear file path should be provided.
     *
     * @return Logger|null
     */
    public static function init(): Logger | null
    {
        $pionia = new Pionia();

        $serverSettings = $pionia::getServerSettings();

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
            $destination = 'terminal';
        }

        if ($destination === 'terminal') {
            $stream = "php://stdout";
        } else if (is_file($destination)) {
            $stream = $destination;
        } else {
            $stream = "php://stdout";
        }

        if ($logRequests || $debug) {
            $maximumLogLevel = Level::Debug;
        } else {
            $maximumLogLevel = Level::Info;
        }

        $logger = new Logger(self::$name);

        if ($debug) {
            $logger
                ->pushProcessor(new ProcessIdProcessor())
                ->pushProcessor(new GitProcessor())
                ->pushProcessor(new MemoryUsageProcessor());
        } else {
            $logger->pushProcessor(new MemoryUsageProcessor());
        }

        if ($debug) {
            $output = self::$name . " :: %level_name% | %datetime% > %message% \n| %context% %extra%\n";
            $formatter = new LineFormatter($output);
        } else {
            $formatter = new JsonFormatter();
        }

        // stream handler for dev
        if ($logRequests || $debug) {
            $stream_handler = new StreamHandler('php://stdout', $maximumLogLevel);
            $stream_handler->setFormatter($formatter);
            $logger->pushHandler($stream_handler);
        }

        // this shall kick in in production
        if ($destination !== 'terminal' && $logRequests) {
            $rotater = new RotatingFileHandler($stream);
            $rotater->setFormatter($formatter);
            $logger->pushHandler($rotater);
        }

        // if we are in production and have the logging settings, then we handle that too
        return $logger;
    }

}