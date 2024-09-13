<?php

namespace Pionia\Utils;

use Pionia\Cache\PioniaCache;
use Pionia\Http\Request\Request;
use Pionia\Http\Response\BaseResponse;
/**
 * Checks and returns cached moonlight endpoints.
 */
trait CachedEndpoints
{
    public static function cacheResponse(Request $request):BaseResponse|bool
    {
        try {
            $cache = app()->getSilently(PioniaCache::class);

            if ($cache) {
                $service = $request->getData()->get('service');
                $action = $request->getData()->get('action');
                if ($service && $action) {
                    $key = Support::toSnakeCase($service . '_' . $action);
                    if ($key && $cache->has($key)) {
                        $cached =  $cache->get($key, false);
                        if ($cached) {
                            $decoded = json_decode($cached);
                            return response(
                                $decoded->returnCode ?? 0,
                                $decoded->returnMessage ?? null,
                                $decoded->returnData ?? null,
                                $decoded->extraData ?? null
                            );
                        }
                    }
                }
            }
        } catch (\Exception $e){
            logger()->warning($e);
        }
        return false;
    }

}
