<?php

namespace jetPhp\core;

use jetPhp\request\Request;
use jetPhp\response\BaseResponse;

abstract class BaseApiController extends Base
{
    public static array | null $settings = null;

    public function __construct()
    {
        parent::__construct();
        if (is_null(static::$settings)){
            static::$settings = $this::resolveSettingsFromIni();
        }
    }

    /**
     * This just is for checking the server status
     */
    public function ping(Request $request): BaseResponse
    {
        return BaseResponse::JsonResponse(0, 'pong', [
            'framework' => $this::$name,
            'version'=> $this::$version,
            'port' => $request->getPort(),
            'uri' => $request->getRequestUri(),
            'schema' => $request->getScheme(),
        ]);
    }
}
