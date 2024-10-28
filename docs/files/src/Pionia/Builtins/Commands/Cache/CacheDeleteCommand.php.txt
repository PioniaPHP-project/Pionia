<?php

namespace Pionia\Builtins\Commands\Cache;

use Pionia\Cache\PioniaCacheAdaptor;
use Pionia\Console\BaseCommand;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Cache\Adapter\Psr16Adapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\Console\Input\InputArgument;

class CacheDeleteCommand extends BaseCommand
{
    protected array $aliases = ['cache:d', 'c:d', 'cache:drop', 'uncache'];

    protected string $name = 'cache:delete';

    protected string $description = 'Delete an item in the cache.';

    protected string $help = 'Remove/Delete an item from the cache by its key';

    public function getArguments(): array
    {
        return [
            ['key', InputArgument::REQUIRED, 'The key of the to delete'],
        ];
    }

    public function handle(): void
    {
        $key = $this->argument('key');

        $cache = $this->cacheInstance();
        if ($cache) {
            try {
                if ($cache->hasItem($key)) {
                    $deleted = $cache->getItem($key);
                    $this->info(strval($deleted->get()));
                } else {
                    $this->info("Cache Item not found");
                }
            } catch (InvalidArgumentException  $exception){
                $this->error($exception->getMessage());
            }
        }
    }

    private function cacheInstance(): ?Psr16Adapter
    {
        $cacheInstance = $this->getApp()->getSilently(Psr16Adapter::class);
        if ($cacheInstance){
            $this->info("Found the cache instance");
            return $cacheInstance;
        }
        return null;
    }
}
