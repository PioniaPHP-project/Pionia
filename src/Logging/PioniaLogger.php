<?php

namespace Pionia\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;
use Monolog\Processor\GitProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\UidProcessor;
use Pionia\core\Pionia;

use Monolog\Handler\StreamHandler; // The StreamHandler sends log messages to a file on your disk
use Monolog\Logger;
use Pionia\exceptions\BaseException;

// The Logger instance
class PioniaLogger extends Pionia
{


    public function __construct()
    {
        parent::__construct();
    }

//
//
//require __DIR__ . "/vendor/autoload.php";
//
//. . .
//use Monolog\Formatter\LineFormatter;
//
//$logger = new Logger("my_logger");
//
//$stream_handler = new StreamHandler("php://stdout", Level::Debug);
//$output = "%level_name% | %datetime% > %message% | %context% %extra%\n";
//$stream_handler->setFormatter(new LineFormatter($output));
//
//$logger->pushHandler($stream_handler);
//
//$logger->debug("This file has been executed")

    public static function init(): Logger | null
    {
        self::resolveSettingsFromIni();

        $debug = self::getServerSettings()["DEBUG"];
        $logRequests = self::getServerSettings()["LOG_REQUESTS"];

        if (!$debug && !$logRequests) {
            return null;
        }

        $destination = self::getServerSettings()["LOG_DESTINATION"];

        if ($destination === 'terminal') {
            $stream = "php://stdout";
        } else if (is_file($destination)) {
            $stream = $destination;
        } else {
            throw new BaseException('Destination file for logs in the settings is not valid');
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
                ->pushProcessor(new GitProcessor());
        } else {
            $logger->pushProcessor(new MemoryUsageProcessor());
        }

        if (!$debug) {
            $output = self::$name . " :: %level_name% | %datetime% > %message% | %context% %extra%\n";
            $formatter = new LineFormatter($output);
        } else {
            $formatter = new JsonFormatter();
        }

        // stream handler for dev
        if ($logRequests) {
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