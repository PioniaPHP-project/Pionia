<?php

namespace Pionia\Validations;

use Nette\Utils\Validators;
use Pionia\Collections\Arrayable;
use Pionia\Exceptions\ValidationException;

trait ValidationTrait
{
    private string $phone_pattern = "/^[+]{1}(?:[0-9\-\\(\\)\\/.]\s?){6,15}[0-9]{1}$/";

    private string $password_pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*(_|[^\w])).{8,}$/";

    private string $slug_pattern = "/^[a-z0-9-]+$/";

    private string $uuid_pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * @throws ValidationException
     */
    protected function validationFail(string $message): never
    {
        throw new ValidationException($message);
    }

    /**
     * @throws ValidationException
     */
    private function _validate(string $regex, ?string $message = 'Invalid data'): bool
    {
        $value = $this->getOrFail();
        $checker = filter_var($value, FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => $regex]]);
        if (!$checker) {
            $this->validationFail($message);
        }

        return true;
    }

    /**
     * @throws ValidationException
     */
    private function getOrFail(): mixed
    {
        if (!$this->hayStack->has($this->hook)) {
            $this->validationFail("{$this->hook} is undefined in the validation data");
        }

        return $this->hayStack->get($this->hook);
    }

    public static function validate(string $keyToValidate, ?Arrayable $data): static
    {
        $klass = new static();
        $klass->hook = $keyToValidate;
        $klass->hayStack = $data;

        return $klass;
    }

    /**
     * @throws ValidationException
     */
    public function required(?string $message = null): static
    {
        $message ??= "{$this->hook} is required";
        $value = $this->hayStack->get($this->hook);
        if ($value === null || blank($value)) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * Accept integers and numeric strings from JSON bodies.
     *
     * @throws ValidationException
     */
    public function integer(?string $message = null): static
    {
        $message ??= "{$this->hook} must be an integer";
        $value = $this->hayStack->get($this->hook);

        if (is_int($value)) {
            return $this;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value)) {
            return $this;
        }

        $this->validationFail($message);
    }

    /**
     * @throws ValidationException
     */
    public function int(?string $message = null): static
    {
        return $this->integer($message);
    }

    /**
     * @throws ValidationException
     */
    public function string(?string $message = null): static
    {
        $message ??= "{$this->hook} must be a string";
        $value = $this->hayStack->get($this->hook);
        if (!is_string($value)) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * Accepts true/false, 0/1, and "true"/"false" strings.
     *
     * @throws ValidationException
     */
    public function boolean(?string $message = null): static
    {
        $message ??= "{$this->hook} must be a boolean";
        $value = $this->hayStack->get($this->hook);

        if (is_bool($value)) {
            return $this;
        }

        if (in_array($value, [0, 1, '0', '1', 'true', 'false', 'yes', 'no'], true)) {
            return $this;
        }

        $this->validationFail($message);
    }

    /**
     * @throws ValidationException
     */
    public function bool(?string $message = null): static
    {
        return $this->boolean($message);
    }

    /**
     * @throws ValidationException
     */
    public function float(?string $message = null): static
    {
        $message ??= "{$this->hook} must be a float";
        $value = $this->hayStack->get($this->hook);
        if (!is_float($value) && !(is_numeric($value) && str_contains((string) $value, '.'))) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function array(?string $message = null): static
    {
        $message ??= "{$this->hook} must be an array";
        $value = $this->hayStack->get($this->hook);
        if (!is_array($value)) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function date(?string $message = null): static
    {
        $message ??= "{$this->hook} must be a valid date";
        $value = $this->hayStack->get($this->hook);
        if (!is_string($value) || strtotime($value) === false) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function email(?string $message = null): static
    {
        $message ??= "{$this->hook} must be a valid email address";
        $value = $this->hayStack->get($this->hook);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function asEmail(?string $regex = null, ?string $message = 'Invalid email address'): static
    {
        if ($regex) {
            $this->_validate($regex, $message);

            return $this;
        }

        return $this->email($message);
    }

    /**
     * @throws ValidationException
     */
    public function asInternationalPhone(?string $code = null, ?string $regex = null, ?string $message = 'Invalid phone number'): static
    {
        $phone = (string) $this->getOrFail();

        if ($code !== null && $code !== '' && !str_starts_with($phone, $code)) {
            $this->validationFail($message);
        }

        $this->_validate($regex ?? $this->phone_pattern, $message);

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function asPassword(?string $regex = null, ?string $message = 'Weak password'): static
    {
        $this->_validate($regex ?? $this->password_pattern, $message);

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function asNumber(?string $message = 'Invalid number'): static
    {
        $number = $this->getOrFail();
        if (!Validators::isNumber($number)) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function asNumeric(?string $message = 'Invalid numeric value'): static
    {
        $number = $this->getOrFail();
        if (!Validators::isNumeric($number)) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function asNumericInt(?string $message = 'Invalid integer'): static
    {
        return $this->integer($message);
    }

    /**
     * @throws ValidationException
     */
    public function asUrl(?string $regex = null, ?string $message = 'Invalid URL'): static
    {
        if ($regex) {
            $this->_validate($regex, $message);

            return $this;
        }

        $url = $this->getOrFail();
        if (!Validators::isUrl($url)) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function asIp(?string $regex = null, ?string $message = 'Invalid IP address'): static
    {
        if ($regex) {
            $this->_validate($regex, $message);

            return $this;
        }

        $this->_validateFilter(FILTER_VALIDATE_IP, $message);

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function asMac(?string $regex = null, ?string $message = 'Invalid MAC address'): static
    {
        if ($regex) {
            $this->_validate($regex, $message);

            return $this;
        }

        $this->_validateFilter(FILTER_VALIDATE_MAC, $message);

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function asDomain(?string $regex = null, ?string $message = 'Invalid domain'): static
    {
        if ($regex) {
            $this->_validate($regex, $message);

            return $this;
        }

        $this->_validateFilter(FILTER_VALIDATE_DOMAIN, $message);

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function asSlug(?string $regex = null, ?string $message = 'Invalid slug'): static
    {
        $this->_validate($regex ?? $this->slug_pattern, $message);

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function uuid(?string $message = 'Invalid UUID'): static
    {
        $this->_validate($this->uuid_pattern, $message);

        return $this;
    }

    /**
     * Validate a 26-character Crockford ULID via {@see \Pionia\Utils\Ulid::isValid()}.
     *
     * Pipe rule: `ulid`
     *
     * @throws ValidationException
     */
    public function ulid(?string $message = 'Invalid ULID'): static
    {
        $value = (string) $this->getOrFail();

        if (!\Pionia\Utils\Ulid::isValid($value)) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * Validate a one-time code length and charset via {@see \Pionia\Security\Security::isOtp()}.
     *
     * Pipe rule: `otp` or `otp:8` (length parameter, default 6)
     *
     * @throws ValidationException
     */
    public function asOtp(int $length = 6, bool $numericOnly = true, ?string $message = null): static
    {
        $value = (string) $this->getOrFail();

        if (!\Pionia\Security\Security::isOtp($value, $length, $numericOnly)) {
            $this->validationFail($message ?? 'Invalid OTP');
        }

        return $this;
    }

    /**
     * Validate API/session token entropy via {@see \Pionia\Security\Security::isToken()}.
     *
     * Pipe rule: `token` or `token:24` (minimum bytes, default 16)
     *
     * @throws ValidationException
     */
    public function asToken(int $minBytes = 16, ?string $message = 'Invalid token'): static
    {
        $value = (string) $this->getOrFail();

        if (!\Pionia\Security\Security::isToken($value, $minBytes)) {
            $this->validationFail($message);
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function regex(string $pattern, ?string $message = 'Invalid format'): static
    {
        $this->_validate($pattern, $message);

        return $this;
    }

    /**
     * Apply a custom rule registered on the shared ValidationManager.
     *
     * @throws ValidationException
     */
    public function rule(string $name, ?string $parameter = null): static
    {
        app()->get(ValidationManager::class)->apply($name, $this, $this->hook, $parameter, $this->hayStack);

        return $this;
    }

    /**
     * Minimum numeric value, string length, or array item count.
     *
     * @throws ValidationException
     */
    public function min(int|float $minimum, ?string $message = null): static
    {
        $value = $this->hayStack->get($this->hook);

        if (is_array($value)) {
            if (count($value) < $minimum) {
                $this->validationFail($message ?? "{$this->hook} must have at least {$minimum} items");
            }

            return $this;
        }

        if (is_numeric($value)) {
            if ((float) $value < $minimum) {
                $this->validationFail($message ?? "{$this->hook} must be at least {$minimum}");
            }

            return $this;
        }

        if (is_string($value)) {
            if (mb_strlen($value) < $minimum) {
                $this->validationFail($message ?? "{$this->hook} must be at least {$minimum} characters");
            }

            return $this;
        }

        $this->validationFail($message ?? "{$this->hook} must be at least {$minimum}");
    }

    /**
     * Maximum numeric value, string length, or array item count.
     *
     * @throws ValidationException
     */
    public function max(int|float $maximum, ?string $message = null): static
    {
        $value = $this->hayStack->get($this->hook);

        if (is_array($value)) {
            if (count($value) > $maximum) {
                $this->validationFail($message ?? "{$this->hook} must not exceed {$maximum} items");
            }

            return $this;
        }

        if (is_numeric($value)) {
            if ((float) $value > $maximum) {
                $this->validationFail($message ?? "{$this->hook} must not exceed {$maximum}");
            }

            return $this;
        }

        if (is_string($value)) {
            if (mb_strlen($value) > $maximum) {
                $this->validationFail($message ?? "{$this->hook} must not exceed {$maximum} characters");
            }

            return $this;
        }

        $this->validationFail($message ?? "{$this->hook} must not exceed {$maximum}");
    }

    /**
     * @throws ValidationException
     */
    public function between(int|float $minimum, int|float $maximum, ?string $message = null): static
    {
        $this->min($minimum, $message ?? "{$this->hook} must be between {$minimum} and {$maximum}");
        $this->max($maximum, $message ?? "{$this->hook} must be between {$minimum} and {$maximum}");

        return $this;
    }

    /**
     * @param list<string> $allowed
     *
     * @throws ValidationException
     */
    public function in(array $allowed, ?string $message = null): static
    {
        $value = $this->hayStack->get($this->hook);
        if (!in_array((string) $value, $allowed, true)) {
            $this->validationFail($message ?? "{$this->hook} must be one of: " . implode(', ', $allowed));
        }

        return $this;
    }

    /**
     * @param list<string> $denied
     *
     * @throws ValidationException
     */
    public function notIn(array $denied, ?string $message = null): static
    {
        $value = $this->hayStack->get($this->hook);
        if (in_array((string) $value, $denied, true)) {
            $this->validationFail($message ?? "{$this->hook} contains a forbidden value");
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function requiredIf(string $field, callable $condition): static
    {
        if ($condition($this->valueOf($field))) {
            return $this->required();
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function requiredUnless(string $field, callable $condition): static
    {
        if (!$condition($this->valueOf($field))) {
            $this->required();
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function requiredWith(string $field): static
    {
        if ($this->hayStack->has($field) && !blank($this->hayStack->get($field))) {
            $this->required();
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function requiredWithout(string $field): static
    {
        if (!$this->hayStack->has($field) || blank($this->hayStack->get($field))) {
            $this->required();
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function matches(string $field, ?string $message = null): static
    {
        $value = $this->hayStack->get($this->hook);
        $match = $this->hayStack->get($field);

        if ($value === null || blank($value)) {
            $this->validationFail("{$this->hook} is required");
        }

        if ($match === null || blank($match)) {
            $this->validationFail("{$field} is required");
        }

        if ($value !== $match) {
            $this->validationFail($message ?? "{$this->hook} does not match {$field}");
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    public function doesNotMatch(string $field, ?string $message = null): static
    {
        $value = $this->hayStack->get($this->hook);
        $match = $this->hayStack->get($field);

        if ($value === null || blank($value)) {
            $this->validationFail("{$this->hook} is not provided");
        }

        if ($match === null || blank($match)) {
            $this->validationFail("{$field} is not provided");
        }

        if ($value === $match) {
            $this->validationFail($message ?? "{$this->hook} must not match {$field}");
        }

        return $this;
    }

    /**
     * @throws ValidationException
     */
    private function _validateFilter(int $filterType, string $message = 'Invalid data'): bool
    {
        $value = $this->getOrFail();
        $checker = filter_var($value, $filterType);
        if ($checker === false) {
            $this->validationFail($message);
        }

        return true;
    }
}
