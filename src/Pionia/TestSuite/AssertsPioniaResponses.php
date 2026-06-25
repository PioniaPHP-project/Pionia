<?php

namespace Pionia\TestSuite;

use Pionia\Collections\Arrayable;
use Pionia\Http\Response\BaseResponse;
use Pionia\Realm\AppRealm;

trait AssertsPioniaResponses
{
    protected function decodeBaseResponse(BaseResponse $response): array
    {
        return json_decode($response->getPrettyResponse(), true, 512, JSON_THROW_ON_ERROR);
    }

    protected function ensureSwitchContextIsArrayable(): void
    {
        $app = app();
        $switches = $app->getSilently(AppRealm::SWITCHES_TAGS);
        $services = $app->getSilently(AppRealm::SERVICES_TAG);

        $switchData = $switches instanceof Arrayable ? $switches->all() : (array) ($switches ?? []);
        $serviceData = $services instanceof Arrayable ? $services->all() : (array) ($services ?? []);

        $app->set(AppRealm::SWITCHES_TAGS, arr($switchData));
        $app->set(AppRealm::SERVICES_TAG, arr($serviceData));
    }
}
