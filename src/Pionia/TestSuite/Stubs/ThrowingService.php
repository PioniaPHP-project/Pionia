<?php

namespace Pionia\TestSuite\Stubs;

use Pionia\Collections\Arrayable;
use Pionia\Http\Response\BaseResponse;
use Pionia\Http\Services\Service;
use RuntimeException;
use Pionia\Http\Bag\FileBag;

class ThrowingService extends Service
{
    protected function boomAction(Arrayable $data, ?FileBag $files = null): BaseResponse
    {
        throw new RuntimeException('kaboom');
    }
}
