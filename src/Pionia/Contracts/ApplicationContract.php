<?php

namespace Pionia\Contracts;

use Pionia\Http\Response\Response;
use Pionia\Utils\PioniaApplicationType;
use Pionia\Http\Response\BinaryFileResponse;

interface ApplicationContract
{

    function appType(): PioniaApplicationType;

    function fly(): int | Response | BinaryFileResponse;

    function boot_internal(): ApplicationContract;
}
