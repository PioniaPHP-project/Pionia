<?php
namespace  Pionia\Validations;

use Exception;
use Nette\Utils\Validators;
use Pionia\Collections\Arrayable;

/**
 */
trait ValidationTrait
{
    private  string $phone_pattern = "/^[+]{1}(?:[0-9\-\\(\\)\\/.]\s?){6,15}[0-9]{1}$/";
    private  string $password_pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*(_|[^\w])).+$/";
    private  string $ip_pattern = "/^(\d{1,3}\.){3}\d{1,3}$/";
    private  string $slug_pattern = "/^[a-z0-9-]+$/";
    private bool $throwsExceptions = true;

    /**
     * Use this to cover scenarios this contract does not cover
     * @param string $regex - The regular expression to check against
     * @param string|null $message - The message to throw if the value is invalid and we are in the exceptions mode
     * @return bool|int
     * @throws Exception
     */
    private function _validate(string $regex, ?string $message = 'Invalid data'): bool|int
    {
        $value = $this->getOrFail();
        $checker = filter_var($value, FILTER_VALIDATE_REGEXP,  ['options' => ['regexp' => $regex]]);
        if (!$checker && $this->throwsExceptions) {
            throw new Exception($message);
        }
        return $checker;
    }

    /**
     * @throws Exception
     */
    private function getOrFail()
    {
        return $this->hayStack->has($this->hook) ? $this->hayStack->get($this->hook) : throw new Exception("$this->hook is undefined in the validation data");
    }

    /**
     * Validates emails of all formats
     * @param string|null $regex
     * @param string|null $message
     * @return Validator
     * @throws Exception
     */
    public  function asEmail(string $regex = null, ?string $message = 'Invalid email address'): Validator
    {
        if ($regex){
            $this->_validate($regex, $message);
            return $this;
        }

        $email = $this->getOrFail();
        Validators::isEmail($email) ?: throw new Exception($message);
        return $this;
    }

    /**
     * Will only validate international numbers if the code is provided, otherwise, will validate local only
     *
     * @param string|null $code International country that you want to check against
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @param string|null $message
     * @return bool|int
     * @throws Exception
     */
    public function asInternationalPhone(?string $code = null, ?string $regex = null, ?string $message = 'Invalid phone number'): bool|int
    {
        $phone = $this->getOrFail();
        // we have the regex but no code
        if (!$code){
            return $this->_validate($regex ?? $this->phone_pattern);
        }

        $copy = $phone;
        if (!str_starts_with($copy, $code)){
            throw new Exception($message);
        }
        return $this->_validate($regex ?? $this->phone_pattern, $message);
    }

    /**
     * Validates the rules as follows
     *  - At least one integer
     *  - At least one lowercase alpha letter
     *  - At least one Uppercase alpha letter
     *  - At least one special character
     *
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @throws Exception
     */
    public  function asPassword(?string $regex = null, ?string $message = 'Week Password'): static
    {
        $this->_validate($regex ?? $this->password_pattern, $message);
        return $this;
    }

    /**
     * @throws Exception
     */
    public function asNumber(?string $message = 'Invalid Number'): static
    {
        $number = $this->getOrFail();
        Validators::isNumber($number) ?? throw new Exception($message);
        return $this;
    }

    /**
     * @throws Exception
     */
    public function asNumeric(?string $message = 'Invalid Numeric'): static
    {
        $number = $this->getOrFail();
        Validators::isNumeric($number) ?: throw new Exception($message);
        return $this;
    }

    /**
     * @throws Exception
     */
    public function asNumericInt(?string $message='Invalid Numeric Integer'): static
    {
        $number = $this->getOrFail();
        if (Validators::isNumericInt($number)){
            return $this;
        }
        throw new Exception($message);
    }

    /**
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @param string|null $message
     * @return Validator
     * @throws Exception
     */
    public  function asUrl(?string $regex = null, ?string $message = 'Invalid URL'): Validator
    {
        $url = $this->getOrFail();
        if ($regex){
            $this->_validate($regex, $message);
            return $this;
        }
        if (Validators::isUrl($url)){
            return $this;
        }
        throw new Exception($message);
    }

    /**
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @param string|null $message
     * @return Validator
     * @throws Exception
     */
    public  function asIp(?string $regex = null, ?string $message='Invalid IP address'): Validator
    {
        if ($regex) {
             $this->_validate($regex, $message);
        } else {
            $this->_validateFilter(FILTER_VALIDATE_IP, $message);
        }
        return $this;
    }

    /**
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return Validator
     * @throws Exception
     */
    public function asMac(?string $regex = null): Validator
    {
        $mac = $this->getOrFail();
        if ($regex) {
            $this->_validate($regex, $mac, 'Invalid MAC Address');
        } else {
            $this->_validateFilter($mac, FILTER_VALIDATE_MAC, 'Invalid Mac Address');
        }
        return $this;
    }

    /**
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return Validator
     * @throws Exception
     */
    public function asDomain(?string $regex = null): Validator
    {
        $domain = $this->getOrFail();
        if ($regex){
            $this->validate($regex, $domain, 'Invalid domain');
        } else {
            $this->_validateFilter($domain, FILTER_VALIDATE_DOMAIN, 'Invalid Domain');
        }
        return $this;
    }


    /**
     * @param string|null $regex Your custom regular expression we can depend on instead.
     *
     * @return Validator
     * @throws Exception
     * @example ```
     *      $slug = 'fsjkfjshfsjk-skdhfkjdfsj-skdjfhjskdf'; // valid slug
     *      $slug2 = 'sfksdfsdskljfhsdhjkfhsdsfsdfsfsd'; // valid slug
     *      $slug3 = 'dkfl ksjfhsdk/skjdfsk%'; // invalid slug
     */
    public  function asSlug(?string $regex = null): Validator
    {
        $slug = $this->getOrFail();
        $this->_validate($regex ?? $this->slug_pattern, $slug, 'Invalid slug');
        return $this;
    }


    /**
     * Internal validator based on PHP filter_var validations
     * @param $filterType
     * @param string $message
     * @return int|bool
     * @throws Exception
     */
    private function _validateFilter($filterType, string $message = 'Invalid Data'): int | bool
    {
        $value = $this->getOrFail();
        $checker = filter_var($value, $filterType);
        if (!$checker && $this->throwsExceptions){
            throw new Exception($message);
        }
        return $checker == $value;
    }

    /**
     * Check if the value matches the expected value
     * @param string $expected
     * @param string|null $message
     * @return Validator
     * @throws Exception
     */
    private function is(mixed $expected, ?string $message = 'Invalid data'): Validator
    {
        $value = $this->getOrFail();
        Validators::is($value, $expected) ?? throw new Exception($message);
        return $this;
    }

    /**
     * @throws Exception
     */
    public function are(string $expected, ?string $message = 'Invalid data'): Validator
    {
        $value = $this->getOrFail();
        if (!is_iterable($value)){
            return $this->is($expected, $message);
        }
        Validators::everyIs($value, $expected) ?: throw new Exception($message);
        return $this;
    }

    public static function validate(string $keyToValidate, ?Arrayable $data): static
    {
        $klass = new static();
        $klass->hook = $keyToValidate;
        $klass->hayStack = $data;
        return $klass;
    }

    /**
     * Marks the field as required
     * @throws Exception
     */
    public function required(?string $message = null): static
    {
        $message ??= "$this->hook is required";
        $value = $this->hayStack->get($this->hook) ?? throw new Exception($message);
        if (blank($value)) {
            throw new Exception($message);
        }
        return $this;
    }

    /**
     * Ensures that the field is an integer
     * @throws Exception
     */
    public function int(?string $message = null): static
    {
        $message ??= "$this->hook must be an integer";
        $value = $this->hayStack->get($this->hook);
        if (!is_numeric($value) || !is_int($value)) {
            throw new Exception($message);
        }
        return $this;
    }

    /**
     * Ensures that the field is a string
     * @throws Exception
     */
    public function string(?string $message = null): static
    {
        $message ??= "$this->hook must be a string";
        $value = $this->hayStack->get($this->hook);
        if (!is_string($value)) {
            throw new Exception($message);
        }
        return $this;
    }

    /**
     * Ensures that the field is a boolean
     * @throws Exception
     */
    public function bool(?string $message = null): static
    {
        $message ??= "$this->hook must be a boolean";
        $value = $this->hayStack->get($this->hook);
        if (!is_bool($value)) {
            throw new Exception($message);
        }
        return $this;
    }

    /**
     * Ensures that the field is a float
     * @throws Exception
     */
    public function float(?string $message = null): static
    {
        $message ??= "$this->hook must be a float";
        $value = $this->hayStack->get($this->hook);
        if (!is_float($value)) {
            throw new Exception($message);
        }
        return $this;
    }

    /**
     * Ensures that the field is an array
     * @throws Exception
     */
    public function array(?string $message = null): static
    {
        $message ??= "$this->hook must be an array";
        $value = $this->hayStack->get($this->hook);
        if (!is_array($value)) {
            throw new Exception($message);
        }
        return $this;
    }

    /**
     * Ensures that the field is a date
     * @throws Exception
     */
    public function date(?string $message = null): static
    {
        $message ??= "$this->hook must be a valid date";
        $value = $this->hayStack->get($this->hook);
        if (!strtotime($value)) {
            throw new Exception($message);
        }
        return $this;
    }

    /**
     * Ensures that the field is an email
     * @throws Exception
     */
    public function email(?string $message = null): static
    {
        $message ??= "$this->hook must be a valid email address";
        $value = $this->hayStack->get($this->hook);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new Exception($message);
        }
        return $this;
    }

    /**
     * Required if the condition is met by the hook
     * @param callable $condition The condition to check against. Receives the instance of the validator
     * @return Validator
     * @throws Exception
     */
    public function requiredIf(string $field, callable $condition): static
    {
        if ($condition($this->valueOf($field))) {
            return $this->required();
        }
        return $this;
    }

    /**
     * Required unless the condition is met by the field
     * @throws Exception
     */
    public function requiredUnless(string $field, callable $condition): static
    {
        if (!$condition($this->valueOf($field))) {
            $this->required();
        }
        return $this;
    }

    /**
     * Only required if the field is present/defined
     * @throws Exception
     */
    public function requiredWith(string $field): static
    {
        if ($this->hayStack->has($field) && !blank($this->hayStack->get($field))) {
            $this->required();
        }
        return $this;
    }

    /**
     * Only required if the field is not present/defined
     * @throws Exception
     */
    public function requiredWithout(string $field): static
    {
        if (!$this->hayStack->has($field) || blank($this->hayStack->get($field))) {
            $this->required();
        }
        return $this;
    }

    /**
     * Check if the value of the field is equal to the value of another field
     * @throws Exception
     */
    public function matches(string $field, ?string $message = null): static
    {
        $value = $this->hayStack->get($this->hook);
        $match = $this->hayStack->get($field);
        if (!$value) {
            throw new Exception("$this->hook is required");
        }
        if (!$match) {
            throw new Exception("$field is required");
        }

        if ($value !== $match) {
            throw new Exception($message ?? "$this->hook does not match $field");
        }
        return $this;
    }

    /**
     * Check if the value of the field is not equal to the value of another field
     * @throws Exception
     */
    public function doesNotMatch(string $field, ?string $message = null): static
    {
        $value = $this->hayStack->get($this->hook);
        $match = $this->hayStack->get($field);
        if (!$value) {
            throw new Exception("$this->hook is not provided");
        }
        if (!$match) {
            throw new Exception("$field is not provided");
        }

        if ($value === $match) {
            throw new Exception($message ?? "$this->hook matches $field");
        }
        return $this;
    }
}
