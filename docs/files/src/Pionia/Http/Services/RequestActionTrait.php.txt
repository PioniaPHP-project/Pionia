<?php

namespace Pionia\Http\Services;

use Pionia\Collections\Arrayable;
use Pionia\Exceptions\FailedRequiredException;

/**
 * Provides helper methods for request actions
 *
 * @author [Jet - ezrajet9@gmail.com](https://www.linkedin.com/in/jetezra/)
 */
trait RequestActionTrait
{

    /**
     * Checks if a single field is present in the request data and is not null or empty
     * @param string $field
     * @param Arrayable $data
     * @return void
     * @throws FailedRequiredException
     */
    private static function checkOne(string $field, Arrayable $data): void
    {
        if($data->has($field) && $data->get($field) === null || $data->get($field) === ''){
            throw new FailedRequiredException("Field $field is required!");
        }
    }

    /**
     * This checks if the required fields are present in the request otherwise it throws an exception
     * @param array|string $required The fields that are required
     * @throws FailedRequiredException if a required field is not present in the request
     */
    public function requires(array | string $required = []): void
    {
        $rType = $this->request->getContentTypeFormat();

        $data = $this->request->getData();
        // better algorithm to check if the required fields are present is welcome.
        if ($rType === 'json') {
            // we dont check in files since in json,
            // files are sent as base64 encoded strings too so we can get them from the json data
            if (is_string($required)) {
               self::checkOne($required, $data);
            }
            foreach ($required as $field) {
                self::checkOne($field, $data);
            }
        } else {
            if (is_string($required)) {
                if (!$this->request->getFileByName($required)) {
                    self::checkOne($required, $data);
                }
            }
            foreach ($required as $field) {
                if (!$this->request->getFileByName($field)) {
                    self::checkOne($field, $data);
                }
            }
        }

    }

}
