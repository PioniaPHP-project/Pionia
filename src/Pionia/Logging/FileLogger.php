<?php

namespace Pionia\Logging;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * File-backed log channel (Monolog StreamHandler).
 */
final class FileLogger implements LoggerInterface
{
    private Logger $logger;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(string $path, string $name = 'pionia', array $config = [])
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->logger = new Logger($name);
        $level = Logger::toMonologLevel($config['level'] ?? 'debug');
        $this->logger->pushHandler(new StreamHandler($path, $level));
    }

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}
