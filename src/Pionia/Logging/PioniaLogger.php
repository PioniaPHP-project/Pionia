<?php

namespace Pionia\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\ScalarFormatter;
use Monolog\Formatter\SyslogFormatter;
use Monolog\Handler\ErrorLogHandler;
use Pionia\Collections\Arrayable;
use Psr\Log\LoggerInterface;
use Stringable;

// The StreamHandler sends log messages to a file on your disk

class PioniaLogger implements LoggerInterface
{
    private array $hiddenKeys = ['password', 'pass', 'pin', 'passwd', 'secret_key', 'pwd', 'token', 'credit_card', 'creditcard', 'cc', 'secret', 'cvv', 'cvn'];
    /**
     * @var string
     */
    private string $name;

    /**
     * @var ?Arrayable log handlers to use
     */
    private ?Arrayable $handlers;

    /**
     * @var ?FormatterInterface The formatter to use
     */
    private ?FormatterInterface $formatter;

    /**
     * @var LoggerInterface | null The base logger we shall rely on. By default it is the Monolog Logger
     */
    private null | LoggerInterface $baseLogger;

    /**
     * @var Arrayable The settings for the logger, it is the logging section in the database.ini file or settings from the application
     */
    private Arrayable $settings;

    public function __construct()
    {
        $this->settings = arr(container()->env('logging', [
            'APP_NAME' => 'Pionia',
            'LOG_FORMAT' => 'TEXT',
            'LOG_PROCESSORS' => [],
            'LOG_DESTINATION' => 'stdout',
            'LOG_HANDLERS' => [],
            'HIDE_SUB' => '*********'
        ]));

        $name = container()->getOrDefault('APP_NAME', 'Pionia');

        if ($this->settings->has('APP_NAME')) {
            $name = $this->settings->get('APP_NAME');
        }

        $this->name = $name;

        $handlers = container()->getOrDefault('LOG_HANDLERS', []);

        if ($this->settings->has('HIDE_IN_LOGS')) {
            $this->hiddenKeys = array_merge($this->hiddenKeys, explode(',', $this->settings->get('HIDE_IN_LOGS')));
        }

        container()->set('LOG_HIDDEN_KEYS', Arrayable::toArrayable($this->hiddenKeys));

        if (is_string($handlers)) {
            $handlers = explode(',', $handlers);
        }

        $this->handlers = new Arrayable($handlers);

        // we add the base logger we shall rely on
        $this->baseLogger = container()->make('base_logger', ['name' => $this->name]);

        $this->addFormatter();

        // we add default handlers
        $this->resolveHandlers();
    }

    private function resolveHandlers(): void
    {
        $this->handlers->each(function ($handler) {
            if (is_string($handler)) {
                $handler = trim($handler);
                $handler = new $handler;
            }
            $this->addHandler($handler);
        });
        // this will add the default handler
        $handler = new ErrorLogHandler();
        $handler->setFormatter($this->formatter);
        $this->baseLogger->pushHandler($handler);
    }

    public function addHandler(callable $callable): static
    {
        $handler = $callable($this);
        $handler->setFormatter($this->formatter);
        $this->baseLogger->pushHandler($handler);
        return $this;
    }


    private function addFormatter(?FormatterInterface $formatter = null): void
    {
        if (!$formatter) {
            $outFormat = 'TEXT';

            if ($this->settings->has('LOG_FORMAT')) {
                $outFormat = strtoupper($this->settings->get('LOG_FORMAT'));
            }
            $dateFormat = 'Y-m-d H:i:s';

            if ($outFormat === 'JSON') {
                $formatter = new JsonFormatter(1, true, true, true);
            } else if ($outFormat === 'SCALAR') {
                $formatter = new ScalarFormatter($dateFormat);
            } else if ($outFormat === 'HTML') {
                $formatter = new HtmlFormatter($dateFormat);
            } else if ($outFormat === 'SYSLOG') {
                $formatter = new SyslogFormatter($this->name);
            } else if ($outFormat === 'LINE' || $outFormat === 'TEXT') {
                $output = '[%datetime%] ' . strtolower($this->name) . ".%level_name% >> %message%  %context% %extra%";
                $formatter = new LineFormatter($output, $dateFormat, true, true);
            } else {
                // check in the context if there are any
                $formatters = container()->getSilently('LOG_FORMATTER');
                if ($formatters) {
                    $formatter = new $formatters($dateFormat);
                } else {
                    $formatter = new LineFormatter('[%datetime%] ' . strtolower($this->name) . ".%level_name% >> %message%  %context% %extra%", $dateFormat, true, true);
                }
            }
        }

        if(method_exists($formatter, 'ignoreEmptyContextAndExtra')){
            $formatter->ignoreEmptyContextAndExtra();
        }
        if (method_exists($formatter, 'setJsonPrettyPrint')) {
            $formatter->setJsonPrettyPrint(true);
        }
        $this->formatter = $formatter;
    }

    /**
     * This method will hide the secure keys in the logs
     * @param array $data The data whose secure keys are to be hidden
     * @return array The data with the hidden keys hidden
     */
    public function hideInLogs(mixed $data = []): array
    {
        if (!is_array($data)) {
            return [];
        }
        // this method will hide the secured keys in the logs
        $keys = $this->hiddenKeys;
        $sub = $this->settings->has('HIDE_SUB') ? $this->settings->get('HIDE_SUB') : '*********';


        array_walk_recursive($data, function (&$value, $key) use ($keys, $sub) {
            if (in_array($key, $keys)) {
                $value = $sub;
            }
        });

        return $data;
    }

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $context = $this->hideInLogs($context);
        count($context) > 0 ? $this->baseLogger->emergency($message, $context) : $this->baseLogger->emergency($message);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $context = $this->hideInLogs($context);
        count($context) > 0 ? $this->baseLogger->alert($message, $context) : $this->baseLogger->alert($message);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $context = $this->hideInLogs($context);
        count($context) > 0 ? $this->baseLogger->critical($message, $context) : $this->baseLogger->critical($message);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $context = $this->hideInLogs($context);
        count($context) > 0 ? $this->baseLogger->error($message, $context) : $this->baseLogger->error($message);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $context = $this->hideInLogs($context);
        count($context) > 0 ? $this->baseLogger->warning($message, $context) : $this->baseLogger->warning($message);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $context = $this->hideInLogs($context);
        count($context) > 0 ? $this->baseLogger->notice($message, $context) : $this->baseLogger->notice($message);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $context = $this->hideInLogs($context);
        count($context) > 0 ? $this->baseLogger->info($message, $context) : $this->baseLogger->info($message);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $context = $this->hideInLogs($context);
        count($context) > 0 ? $this->baseLogger->debug($message, $context) : $this->baseLogger->debug($message);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        $context = $this->hideInLogs($context);
        count($context) > 0 ? $this->baseLogger->log($level, $message, $context) : $this->baseLogger->log($level, $message);
    }
}
