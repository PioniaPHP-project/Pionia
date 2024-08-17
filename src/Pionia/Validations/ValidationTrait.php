<?php
namespace  Pionia\Pionia\Validations;

use Exception;
use Nette\Utils\Validators;

/**
 */
trait ValidationTrait
{
    public  string $phone_pattern = "/^[+]{1}(?:[0-9\-\\(\\)\\/.]\s?){6,15}[0-9]{1}$/";
    public  string $password_pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*(_|[^\w])).+$/";
    public  string $ip_pattern = "/^(\d{1,3}\.){3}\d{1,3}$/";
    public  string $slug_pattern = "/^[a-z0-9-]+$/";
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
    public  function asInternationalPhone(?string $code = null, ?string $regex = null, ?string $message = 'Invalid phone number'): bool|int
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
    public  function asMac(?string $regex = null): Validator
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
     * @param mixed $slug The slug string we are testing
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
     * @throws Exception
     */
    private function shouldBe($expected, ?string $message = 'Invalid data'): Validator
    {
        $value = $this->getOrFail();
        Validators::is($value, $expected) ?:throw new Exception($message);
        return $this;
    }

    /**
     * @throws Exception
     */
    public function allShouldBe(string $expected, ?string $message = 'Invalid data'): Validator
    {
        $value = $this->getOrFail();
        if (!is_iterable($value)){
            return $this->shouldBe($expected, $message);
        }
        Validators::everyIs($value, $expected) ?: throw new Exception($message);
        return $this;
    }
}
