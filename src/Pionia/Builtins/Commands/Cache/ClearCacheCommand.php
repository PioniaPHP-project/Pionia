<?php

namespace Pionia\Builtins\Commands\Cache;

use Pionia\Cache\PioniaCache;
use Pionia\Console\BaseCommand;
use Pionia\Templating\TemplateEngine;

class ClearCacheCommand extends BaseCommand
{
    protected array $aliases = ['cache:c', 'c:c', ];

    protected string $name = 'cache:clear';

    protected string $description = 'Clear all the expired cashed data.';

    protected string $help = 'Manually clear off all cached data. This will remove both expired and non-expired data. \n
     If you only want to remove expired data, please run `cache:prune` command instead.';

    public function handle(): void
    {
        $cache = $this->cacheInstance();
        if ($cache) {
            $cleard = $cache->clear();
            if ($cleard){
                $this->info('All cached data has been cleared');
            }
        }

        TemplateEngine::clearCache();
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
