<?php

namespace Pionia\Builtins\Commands\Cache;

use Pionia\Cache\PioniaCache;
use Pionia\Console\BaseCommand;

class PruneCacheCommand extends BaseCommand
{
    protected array $aliases = ['cache:p', 'c:p', ];

    protected string $name = 'cache:prune';

    protected string $description = 'Clear all the expired cashed data.';

    protected string $help = 'Manually prune off all data that is already expired from your caches cached';

    public function handle(): void
    {
        $cache = $this->cacheInstance();
        if ($cache) {
            $pruned = $cache->prune();
            if ($pruned){
                $this->info('All expired cached data has been pruned');
            }
        }
    }

    private function cacheInstance(): ?PioniaCache
    {
        $cacheInstance = $this->getApp()->getSilently(PioniaCache::class);
        if ($cacheInstance){
            $this->info("Found the cache instance");
            return $cacheInstance;
        }
        return null;
    }
}
