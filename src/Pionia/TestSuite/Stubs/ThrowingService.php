<?php

namespace Pionia\TestSuite\Stubs;

use Pionia\Collections\Arrayable;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Services\Service;
use RuntimeException;
use Symfony\Component\HttpFoundation\FileBag;

class ThrowingService extends Service
{
    protected function boomAction(Arrayable $data, ?FileBag $files = null): BaseResponse
    {
        throw new RuntimeException('kaboom');
    }
}
