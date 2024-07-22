<?php

namespace Pionia\Core;

use Pionia\Request\Request;
use Pionia\Response\BaseResponse;

/**
 * This is the base controller class for the framework and most probably the only controller class that should be extended.
 *
 * It is used to define the base controller for the project.
 * According to this framework, only controller classes should be used in the entire app. That's why it is called a controller anyway!
 * Therefore, one class in the entire should extend this class. and provide the implementation as per the project requirements.
 *
 * It should call the main switch's processService method to process the request and return the response.
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 * */
abstract class BaseApiController extends Pionia
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
     * This is just for checking the server status
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
