<?php

namespace Pionia\Base;


use Exception;
use Pionia\Base\Events\PioniaConsoleStarted;
use Pionia\Cache\Cacheable;
use Pionia\Collections\Arrayable;
use Pionia\Console\BaseCommand;
use Pionia\Contracts\ApplicationContract;
use Pionia\Realm\AppRealm;
use Pionia\Utils\PioniaApplicationType;
use Pionia\Utils\Support;
use Symfony\Component\Console\Application;
use Symfony\Component\Process\PhpExecutableFinder;

class Pionia extends Application implements ApplicationContract
{
    use Cacheable, AppMixin;
    private ?string $name;
    private ?string $version;
    private AppRealm $realm;
    public function __construct(AppRealm $realm)
    {
        $this->realm = $realm;
        $this->name = $realm->appName;
        $this->version = $realm->appVersion;
        parent::__construct($this->name, $this->version);
    }


    function appType(): PioniaApplicationType
    {
        return PioniaApplicationType::CONSOLE;
    }

    /**
     * Format the given command as a fully-qualified executable command.
     *
     * @param  string  $string
     * @return string
     */
    public static function formatCommandString(string $string): string
    {
        return sprintf('%s %s %s', self::php(), static::pioniaBinary(), $string);
    }

    /**
     * Get the pionia cli binary.
     */
    public static function pioniaBinary(): string
    {
        return Support::escapeArgument(defined('PIONIA_BINARY') ? PIONIA_BINARY : 'pionia');
    }

    /**
     * Get the PHP binary.
     *
     * @return string
     */
    public static function php(): string
    {
        return Support::escapeArgument((new PhpExecutableFinder)->find(false));
    }

    /**
     * Sync the commands from the configuration and add them to the console application
     */
    public function prepareConsole(): void
    {
        if (! defined('PIONIA_BINARY')) {
            define('PIONIA_BINARY', 'pionia');
        }

        $commands = $this->realm->getOrDefault($this->realm::COMMANDS_TAG, arr([]));

        if ($commands->isFilled()) {
            $commands->each(function (BaseCommand | string $command, $key) {
                if (is_string($command)) {
                    $command = new $command($this, $key);
                }
                $this->add($command);
            });
        }

        realm()->event()->dispatch(new PioniaConsoleStarted($this), PioniaConsoleStarted::name());
    }


    /**
     * @throws Exception
     */
    public function fly(?string $name = null): int
    {
        if ($name === null) {
            $name = $this->name;
        }

        if (PHP_SAPI !== 'cli') {
            echo 'This script can only be run from the command line.';
            exit(1);
        }

        if (version_compare(PHP_VERSION, '8.1.0', '<')) {
            echo 'This script requires PHP 8.1 or later.';
            exit(1);
        }
        $this->powerUp(PioniaApplicationType::CONSOLE);
        // we set the auto exit to false
        $this->setAutoExit(false);
        $this->setName($name);
        $this->setVersion($this->version);
        $this->prepareConsole();
        return $this->run();
    }

    public function realm(): AppRealm
    {
        return $this->realm;
    }
}
