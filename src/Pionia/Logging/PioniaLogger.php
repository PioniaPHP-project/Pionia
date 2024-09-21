<?php

namespace Pionia\Logging;

use DIRECTORIES;
use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\LineFormatter;
use Monolog\Formatter\ScalarFormatter;
use Monolog\Formatter\SyslogFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Pionia\Base\PioniaApplication;
use Pionia\Collections\Arrayable;
use Pionia\Utils\Containable;
use Psr\Log\LoggerInterface;
use Stringable;
use Symfony\Component\Filesystem\Filesystem;

// The StreamHandler sends log messages to a file on your disk

class PioniaLogger implements LoggerInterface
{
    use Containable;

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
     * @var ?Arrayable log processors to use
     */
    private ?Arrayable $processors;

    /**
     * @var ?FormatterInterface The formatter to use
     */
    private ?FormatterInterface $formatter;

    /**
     * @var LoggerInterface |Logger | null The base logger we shall rely on. By default it is the Monolog Logger
     */
    private null | LoggerInterface | Logger $baseLogger = null;

    /**
     * @var Arrayable The settings for the logger, it is the logging section in the database.ini file or settings from the application
     */
    private Arrayable $settings;

    /**
     * @var ?string The destination to log to
     */
    private ?string $destination = null;

    public function __construct(PioniaApplication $app)
    {

        $context = $app->context;
        $this->context = $app->context;

        $this->settings = arr($app->getEnv('logging') ?? [
                'APP_NAME' => 'Pionia',
                'LOG_FORMAT' => 'TEXT',
                'LOG_PROCESSORS' => [],
                'LOG_DESTINATION' => 'stdout',
                'LOG_HANDLERS' => [],
                'HIDE_SUB' => '*********'
            ]);
    
        $name = $this->getOrDefault('APP_NAME', 'Pionia');

        if ($this->settings->has('APP_NAME')) {
            $name = $this->settings->get('APP_NAME');
        }
        $this->name = $name;

        $processors = $this->getOrDefault('LOG_PROCESSORS', []);

        $handlers = $this->getOrDefault('LOG_HANDLERS', []);

        if ($this->settings->has('HIDE_IN_LOGS')) {
            $this->hiddenKeys = array_merge($this->hiddenKeys, explode(',', $this->settings->get('HIDE_IN_LOGS')));
        }

        $context->set('LOG_HIDDEN_KEYS', Arrayable::toArrayable($this->hiddenKeys));

        if (is_string($handlers)) {
            $handlers = explode(',', $handlers);
        }

        if (is_string($processors)) {
            $processors = explode(',', $processors);
        }

        $this->handlers = new Arrayable($handlers);
        $this->processors = new Arrayable($processors);

        // we resolve the destination
        $this->resolveDestination();

        // we add the base logger we shall rely on
        $this->setLogger();

        $this->addFormatter();

        // we add default handlers
        $this->resolveHandlers();
    }

    /**
     * sets up our logger destination.
     * You can also define the LOG_DESTINATION in the logging section of the database.ini file. This must be relative to the logs folder.
     * If you want to log to a file, you can define the LOGS_DESTINATION_FILE in the context. This will be used as the destination.
     */
    public function resolveDestination(?string $destination = null): void
    {
        if ($destination) {
            $this->destination = $destination;
            return;
        }

        $exists = $this->getSilently("LOGS_DESTINATION_FILE");
        if ($exists) {
            $this->destination = $exists;
            return;
        }

        $destination = $this->getOrDefault('LOG_DESTINATION', 'stdout');

        $fs = new Filesystem();

        switch ($destination) {
            case 'stdout':
                $stream = 'php://stdout';
                break;
            case 'stderr':
                $stream = 'php://stderr';
                break;
            default:
                $dir = alias(DIRECTORIES::LOGS_DIR->name);
                $destination = $dir . DIRECTORY_SEPARATOR . $destination;
                if ($fs->exists($destination)) {
                    $stream = $destination;
                } else {
                    $fs->touch($destination);
                    $stream = $destination;
                }
                break;
        }

        $this->destination = $stream;
    }


    public function setLogger(?LoggerInterface $logger = null): void
    {
        $this->baseLogger = $logger ?? new Logger($this->name);
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
                $formatters = $this->getSilently('LOG_FORMATTER');
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
