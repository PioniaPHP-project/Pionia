<?php

namespace JetPhp\Core;

use JetPhp\Request\Request;
use JetPhp\Response\BaseResponse;

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
     * @param Request $request
     */
    public function ping(Request $request): BaseResponse
    {
        return BaseResponse::JsonResponse(0, 'pong', ['framework' => $this::$name, 'version'=> $this::$version]);
    }
}
