<?php

namespace Pionia\Logging;

use Pionia\Collections\Arrayable;
use Pionia\Realm\RealmContract;
use Psr\Log\LoggerInterface;

class LogManager
{
    /** @var array<string, LoggerInterface> */
    private array $channels = [];

    public function __construct(private readonly RealmContract $app)
    {
    }

    public function channel(?string $name = null): LoggerInterface
    {
        $name ??= (string) env('LOG_CHANNEL', 'stack');

        if (isset($this->channels[$name])) {
            return $this->channels[$name];
        }

        $configured = $this->configuredChannels();
        if ($configured->has($name)) {
            return $this->channels[$name] = $this->buildChannel($name, $configured->get($name));
        }

        if (defined('PIONIA_TESTING') && in_array($name, ['stack', 'test'], true)) {
            return $this->channels[$name] = new NullLogger();
        }

        return $this->channels[$name] = new PioniaLogger();
    }

    /**
     * @param array<string, mixed> $config
     */
    public function extend(string $name, array $config): static
    {
        $channels = $this->app->getOrDefault('log.channels', arr([]));
        if (!$channels instanceof Arrayable) {
            $channels = arr((array) $channels);
        }

        $channels->add($name, $config);
        $this->app->set('log.channels', $channels);
        unset($this->channels[$name]);

        return $this;
    }

    private function configuredChannels(): Arrayable
    {
        $channels = $this->app->getOrDefault('log.channels', arr([]));

        return $channels instanceof Arrayable ? $channels : arr((array) $channels);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildChannel(string $name, array $config): LoggerInterface
    {
        $driver = $config['driver'] ?? 'single';

        if ($driver === 'null') {
            return new NullLogger();
        }

        if ($driver === 'single' || $driver === 'stack') {
            return new PioniaLogger();
        }

        return new PioniaLogger();
    }
}
