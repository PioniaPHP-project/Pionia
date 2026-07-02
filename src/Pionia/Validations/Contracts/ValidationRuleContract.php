<?php

namespace Pionia\Validations\Contracts;

use Pionia\Validations\ValidationContext;

/**
 * Class-based custom validation rule.
 */
interface ValidationRuleContract
{
    public function validate(ValidationContext $context): void;
}
