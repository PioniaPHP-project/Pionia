<?php

namespace Pionia\Base;

use Exception;
use Pionia\Base\Events\PioniaConsoleStarted;
use Pionia\Cache\Cacheable;
use Pionia\Console\Application;
use Pionia\Console\Command;
use Pionia\Contracts\ApplicationContract;
use Pionia\Process\PhpExecutable;
use Pionia\Realm\AppRealm;
use Pionia\Utils\PioniaApplicationType;
use Pionia\Utils\Support;

class Pionia extends Application implements ApplicationContract
{
    use Cacheable, AppMixin;

    private AppRealm $realm;

    public function __construct(AppRealm $realm)
    {
        $this->realm = $realm;
        parent::__construct($realm->appName, $realm->appVersion);
    }

    function appType(): PioniaApplicationType
    {
        return PioniaApplicationType::CONSOLE;
    }

    public static function formatCommandString(string $string): string
    {
        return sprintf('%s %s %s', self::php(), static::pioniaBinary(), $string);
    }

    public static function pioniaBinary(): string
    {
        return Support::escapeArgument(defined('PIONIA_BINARY') ? PIONIA_BINARY : 'pionia');
    }

    public static function php(): string
    {
        return Support::escapeArgument(PhpExecutable::find(false));
    }

    public function prepareConsole(): void
    {
        if (! defined('PIONIA_BINARY')) {
            define('PIONIA_BINARY', 'pionia');
        }

        $commands = $this->realm->getOrDefault($this->realm::COMMANDS_TAG, arr([]));

        if ($commands->isFilled()) {
            $commands->each(function (Command|string $command, $key) {
                if (is_string($command)) {
                    $command = new $command($this, $key);
                }
                $this->add($command);
            });
        }

        $this->registerBuiltinCommands();
        $this->markBooted();

        realm()->event()->dispatch(new PioniaConsoleStarted($this), PioniaConsoleStarted::name());
    }

    /**
     * @throws Exception
     */
    public function fly(?string $name = null): int
    {
        if ($name === null) {
            $name = $this->getName();
        }

        if (PHP_SAPI !== 'cli') {
            echo 'This script can only be run from the command line.';
            exit(1);
        }

        if (version_compare(PHP_VERSION, '8.5.0', '<')) {
            echo 'This script requires PHP 8.5 or later.';
            exit(1);
        }
        $this->powerUp(PioniaApplicationType::CONSOLE);
        $this->setAutoExit(false);
        $this->setName($name);
        $this->prepareConsole();

        return $this->run();
    }

    public function realm(): AppRealm
    {
        return $this->realm;
    }
}
