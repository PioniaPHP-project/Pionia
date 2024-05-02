<?php

namespace JetPhp\Response;

/**
 * This provides a uniform response format for our entire application.
 *
 * All requests that hit the application will be handled successfully, so a http status code of 200 OK is expected
 * for all requests when they resolved with an error or not.
 *
 * This gives us an opportunity to define our own status code based on the business requirements.
 *
 * This also helps the server recover gracefully after every panic
 *
 * @property $returnCode defaults to 0 for success. Any other code other than 0 implies an error.
 * This can customized to your needs
 *
 * @property $returnMessage - the message the server wants to send to the frontend
 *
 * @property $returnData - the data the server wants to send to the frontend, can be of any data format
 *
 * @property $extraData - any other data you want to send to the front-end, any!!
 */
class BaseResponse
{

    private int $returnCode = 0;
    private string | null $returnMessage = null;
    private mixed $returnData;
    private mixed $extraData = null;

    private string|null $prettyResponse = null;

    private array | null $response = null;

    public function __construct(mixed $data = null)
    {
        $this->returnData = $data;
    }

    /**
     * @return string|null
     */
    public function getPrettyResponse(): ?string
    {
        return $this->prettyResponse;
    }

    public static function JsonResponse($code=0,  string|null $message = null, mixed $data = null, array | string | null $extraData = null): static
    {
        $response = new BaseResponse($data);
        $response->returnCode=$code;
        $response->returnMessage = $message;
        $response->extraData = $extraData;
        return $response->build();
    }

    public function build($additionalData = []): static
    {
        if ($this->response){
            $data = array_merge($this->response, $additionalData);
        } else {
            $res = [
                'returnCode' => $this->returnCode,
                'returnMessage' => $this->returnMessage,
                'returnData' => $this->returnData,
                'extraData' => $this->extraData
            ];
            $data = array_merge($res, $additionalData);
        }
        $this->prettyResponse =  json_encode($data);
        return $this;
    }
}
