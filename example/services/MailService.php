<?php

/**
 * Demo mail actions for background async() examples.
 *
 * @moonlight-service mail
 * @moonlight-version v1
 * @moonlight-auth none
 */
namespace Application\Services;

use Pionia\Collections\Arrayable;
use Pionia\Http\Bag\FileBag;
use Pionia\Http\Response\ApiResponse;
use Pionia\Http\Services\Service;

class MailService extends Service
{
    /**
     * Send a welcome email (demo — logs only).
     *
     * @moonlight-action send_welcome
     * @moonlight-summary Queued welcome email demo
     * @moonlight-auth none
     * @moonlight-param string email Recipient address
     * @moonlight-example {"service":"mail","action":"send_welcome","email":"user@example.com"}
     */
    protected function sendWelcomeAction(Arrayable $data, ?FileBag $files = null): ApiResponse
    {
        $email = (string) $data->get('email', '');

        if ($email === '') {
            return response(400, 'email is required');
        }

        if (function_exists('logger')) {
            logger()->info('MailService send_welcome', ['email' => $email]);
        }

        return response(0, 'Welcome email queued for delivery', ['email' => $email]);
    }
}
