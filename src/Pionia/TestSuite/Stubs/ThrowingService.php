<?php

namespace Pionia\TestSuite\Stubs;

use Pionia\Collections\Arrayable;
use Pionia\Http\Response\ApiResponse;
use Pionia\Http\Services\Service;
use RuntimeException;
use Pionia\Http\Bag\FileBag;

class ThrowingService extends Service
{
    protected function boomAction(Arrayable $data, ?FileBag $files = null): ApiResponse
    {
        throw new RuntimeException('kaboom');
    }
}
