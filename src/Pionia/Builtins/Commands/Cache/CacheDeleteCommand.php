<?php

namespace Pionia\Builtins\Commands\Cache;

use Pionia\Cache\PioniaCache;
use Pionia\Console\BaseCommand;
use Psr\SimpleCache\InvalidArgumentException;
use Pionia\Console\Input\InputArgument;

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
                if ($cache->has($key)) {
                    $value = $cache->get($key);
                    $cache->delete($key);
                    $this->info(is_scalar($value) ? (string) $value : json_encode($value));
                } else {
                    $this->info('Cache item not found');
                }
            } catch (InvalidArgumentException $exception) {
                $this->error($exception->getMessage());
            }
        }
    }

    private function cacheInstance(): ?PioniaCache
    {
        $cacheInstance = $this->getApp()->getSilently(PioniaCache::class);
        if ($cacheInstance) {
            $this->info('Found the cache instance');

            return $cacheInstance;
        }

        return null;
    }
}
