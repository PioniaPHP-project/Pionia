<?php

namespace jetPhp\request;

use jetPhp\exceptions\FailedRequiredException;

trait RequestActionTrait
{

    private static function check_one($field, $data): void
    {
        if (!array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') {
            throw new FailedRequiredException("The field $field is required");
        }
    }

    /**
     * This checks if the required fields are present in the request otherwise it throws an exception
     * @param Request $request
     * @param array|string $required
     * @return void
     * @throws FailedRequiredException
     */
    public static function requires(Request $request, array | string $required = []): void
    {
        $rType = $request->getContentTypeFormat();

        $data = $request->getData();
        // better algorithm to check if the required fields are present is welcome.
        if ($rType === 'json') {
            // we dont check in files since in json,
            // files are sent as base64 encoded strings too so we can get them from the json data
            if (is_string($required)) {
               self::check_one($required, $data);
            }
            foreach ($required as $field) {
                self::check_one($field, $data);
            }
        } else {
            foreach ($required as $field) {
                if (!$request->getFileByName($field)) {
                    self::check_one($field, $data);
                }
            }
        }

    }

}
