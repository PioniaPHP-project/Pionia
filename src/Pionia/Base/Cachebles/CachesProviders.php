<?php

namespace Pionia\Base\Cachebles;

use Pionia\Collections\Arrayable;
use Pionia\Contracts\ProviderContract;
use Pionia\Utils\Support;

trait CachesProviders
{
    public const PROVIDER_KEY = 'app.providers';

    /**
     * we only want to start resolving only new providers
     * @return Arrayable|null
     */
    private function calculateUnresolvedProviders(): ?Arrayable
    {
        $cached = arr(realm()->cacheInstance()->get(self::PROVIDER_KEY));
        $envProvided = arr(pionia()->env('app_providers', []));
        $builtIns = $this->builtinProviders();
        $all = $builtIns->merge($envProvided);
        if ($cached->isEmpty()){
            return $all;
        }
        if ($all->isEmpty()){
            return arr([]);
        }
        $this->unResolvedAppProviders = $all->differenceFrom($cached);
        return $this->unResolvedAppProviders;
    }

    protected function collectProviders($considerCached = true)
    {
        $providers  = arr([]);
        if ($considerCached){
            $providersArr = app()->cacheInstance()->get(self::PROVIDER_KEY);
            if ($providersArr){
                $providers->merge($providersArr);
            }

            $this->calculateUnresolvedProviders();
            return $this;
        }
        // we only come here if our providers weren't cached already
        // here we re-collect them from the config
        $providers= env()->has('app_providers') ? env()->get('app_providers', []) : [];
        $fineProviders = arr([]);
        arr($providers)->each(function ($value, $key) use ($fineProviders) {
            if (!Support::implements($value, ProviderContract::class)){
                logger()->warning($value.' is not a valid app provider, therefore skipped.');
            }
            $fineProviders->add($key, $value);
        });
        $providersArr = $this->builtinProviders()->merge($fineProviders);
        if ($providersArr->isFilled()){
            realm()->set("app_providers", $providersArr);
            $this->setCache("app_providers", $providersArr->toArray(), $this->appItemsCacheTTL, true);
            $this->appProviders = $providersArr;
            $this->unResolvedAppProviders = $this->calculateUnresolvedProviders();
        }
        return $this;
    }

}
