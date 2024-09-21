<?php

namespace Pionia\Http\Response;

use Pionia\Collections\Arrayable;

/**
 * This provides a uniform response format for our entire application.
 *
 * All requests that hit the application will be handled successfully, so a http status code of 200 OK is expected
 * for all requests whether they resolved with an error or not.
 *
 * This gives us an opportunity to define our own status code based on the business requirements.
 *
 * This also helps the server recover gracefully after every panic since all exceptions will be caught and handled
 *
 * @property $returnCode defaults to 0 for success. Any other code other than 0 implies an error.
 * This can customized to your needs
 *
 * @property $returnMessage - the message the server wants to send to the frontend
 *
 * @property $returnData - the data the server wants to send to the frontend, can be of any data format
 *
 * @property $extraData - any other data you want to send to the front-end, any!!
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
class BaseResponse
{
    private int $returnCode = 0;
    private string | null $returnMessage = null;
    private mixed $returnData;
    private mixed $extraData = null;
    private string|null $prettyResponse = null;

    private array | null $response = null;

    public ?Arrayable $data = null;

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

    /**
     * All actions in Pionia must return this as the response. This is how Pionia ensures a uniform response format
     *
     * @param int|null $code - the return code to the client side. Defaults to 0 for success
     * @param string|null $message - the message to send to the client side
     * @param mixed|null $data - the data to send to the client side
     * @param array|string|null $extraData - any other data to send to the client side
     * @return BaseResponse
     */
    public static function jsonResponse(?int $code = 0, string|null $message = null, mixed $data = null, array | string | null $extraData = null): static
    {
        $response = new BaseResponse($data);
        if ($code === null) {
            $code = 0;
        }
        $response->returnCode=$code;
        $response->returnMessage = $message;
        $response->extraData = $extraData;
        return $response->build();
    }

    /**
     * This is used to build the response.
     *
     * @param array|null $additionalData
     * @return BaseResponse
     */
    public function build(?array $additionalData = []): static
    {
        $data = new Arrayable($this->response ?? []);

        if (count($additionalData) > 0){
            $data->merge($additionalData);
        }

        $res = arr([
            'returnCode' => $this->returnCode ?? 0,
            'returnMessage' => $this->returnMessage ?? null,
            'returnData' => $this->returnData ?? null,
            'extraData' => $this->extraData ?? null,
        ]);

        $this->data = $res;

        $this->prettyResponse = $res->toJson();
        return $this;
    }
}
