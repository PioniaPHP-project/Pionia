<?php
namespace  Pionia\Request;

use Pionia\Exceptions\InvalidDataException;

/**
 */
trait ValidationTrait
{
    public  string $email_pattern = "/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/";
    public  string $phone_pattern = "/^[+]{1}(?:[0-9\-\\(\\)\\/.]\s?){6,15}[0-9]{1}$/";
    public  string $password_pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*(_|[^\w])).+$/";
    public  string $username_pattern = "/^[a-zA-Z0-9_]{5,}$/";
    public  string $date_pattern = "/^\d{4}-\d{2}-\d{2}$/";
    public  string $time_pattern = "/^\d{2}:\d{2}$/";
    public  string $datetime_pattern = "/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/";
    public  string $ip_pattern = "/^(\d{1,3}\.){3}\d{1,3}$/";
    public  string $slug_pattern = "/^[a-z0-9-]+$/";
    private  bool $throwsExceptions = true;
    public function __construct($throws = true)
    {
       $this->throwsExceptions = $throws;
    }

    /**
     * Use this to cover scenarios this contract does not cover
     * @param string $regex - The regular expression to check against
     * @param mixed $value - The value to check
     * @param string|null $message - The message to throw if the value is invalid and we are in the exceptions mode
     * @return bool|int
     * @throws InvalidDataException
     */
    public function validate(string $regex, mixed $value, ?string $message = 'Invalid data'): bool|int
    {
        $checker = filter_var($value, FILTER_VALIDATE_REGEXP,  ['options' => ['regexp' => $regex]]);
//        $checker = preg_match($regex, $value);
        if (!$checker && $this->throwsExceptions) {
            throw new InvalidDataException($message);
        }
        return $checker;
    }

    /**
     * Validates emails of all formats
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @param string $email The email address we're currently testing in the core.
     * @throws InvalidDataException
     */
    public  function asEmail($email, ?string $regex = null): bool|int
    {
        if ($regex) {
            return $this->validate($regex ?? $this->email_pattern, $email, 'Invalid email address');
        }

        return $this->_validateFilter($email, FILTER_VALIDATE_EMAIL, 'Invalid Email Address');
    }

    /**
     * Will only validate international numbers if the code is provided, otherwise, will validate local only
     *
     * @param string $phone The phone number we are testing
     * @param string|null $code International country that you want to check against
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return bool|int
     * @throws InvalidDataException
     */
    public  function asInternationalPhone(string $phone, ?string $code = null, ?string $regex = null): bool|int
    {
        // we have the regex but no code
        if (!$code){
            return $this->validate($regex ?? $this->phone_pattern, $phone);
        }

        $copy = $phone;
        if (!str_starts_with($copy, $code)){
            throw new InvalidDataException('Invalid phone number, must start with '.$code);
        }
        return $this->validate($regex ?? $this->phone_pattern, $copy, 'Invalid phone number');
    }

    /**
     * Validates the rules as follows
     *  - At least one integer
     *  - At least one lowercase alpha letter
     *  - At least one Uppercase alpha letter
     *  - At least one special character
     *
     * @param mixed $password The password string we are testing, must be raw, not hashed
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @throws InvalidDataException
     */
    public  function asPassword(mixed $password, ?string $regex = null): bool|int
    {
        return $this->validate($regex ?? $this->password_pattern, $password, 'Week Password');
    }

    /**
     * Checks for at least 5 alphanumeric letters
     * @param mixed $username The string we are testing
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return bool|int
     * @throws InvalidDataException
     */
    public  function asUsername(mixed $username, ?string $regex = null): bool|int
    {
        return $this->validate($regex ?? $this->username_pattern, $username, 'Invalid username');
    }

    /**
     * @param mixed $date  date in the format `yyyy-mm-dd` eg. '2024-17-05
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return bool|int
     * @throws InvalidDataException
     */
    public  function asDate(mixed $date, ?string $regex = null): bool|int
    {
        return $this->validate($regex ?? $this->date_pattern, $date, 'Invalid date');
    }

    /**
     * @param mixed $number The number we are checking
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return bool|int
     * @throws InvalidDataException
     */
    public function asNumber(mixed $number, ?string $regex = null): bool|int
    {
        if ($regex){
            return $this->validate($number, $regex, 'Invalid number');
        }
        return $this->_validateFilter($number, FILTER_VALIDATE_INT, 'Invalid Integer');
    }

    /**
     * @throws InvalidDataException
     */
    public function asFloat($number, ?string $regex = null): bool|int
    {
        if ($regex){
            return $this->validate($number, $regex, 'Invalid float');
        }

        return $this->_validateFilter($number, FILTER_VALIDATE_FLOAT, 'Invalid Float Number');
    }

    /**
     * @throws InvalidDataException
     */
    public  function asTime($time, ?string $regex = null): bool|int
    {
        return $this->validate($regex ?? $this->time_pattern, $time, 'Invalid time');
    }

    /**
     * @throws InvalidDataException
     */
    public  function asDateTime($date, ?string $regex = null): bool|int
    {
        return $this->validate($regex ?? $this->datetime_pattern, $date, 'Invalid DateTime');
    }

    /**
     * @param mixed $url The url string we are testing
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return bool|int
     * @throws InvalidDataException
     */
    public  function asUrl(mixed $url, ?string $regex = null): bool|int
    {
        if ($regex){
            return $this->validate($url, $regex, 'Invalid URL');
        }

        return $this->_validateFilter($url, FILTER_VALIDATE_URL, 'Invalid Url');
    }

    /**
     * @param mixed $ip The Ip address we're checking.
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return bool|int
     * @throws InvalidDataException
     */
    public  function asIp(mixed $ip, ?string $regex = null): bool|int
    {
        if ($regex) {
            return $this->validate($regex ?? $this->ip_pattern, $ip, 'Invalid IP address');
        }
        return $this->_validateFilter($ip, FILTER_VALIDATE_IP, 'Invalid IP address');
    }

    /**
     * @param mixed $mac The mac address we are checking
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return bool|int
     * @throws InvalidDataException
     */
    public  function asMac(mixed $mac, ?string $regex = null): bool|int
    {
        if ($regex) {
            return $this->validate($regex, $mac, 'Invalid MAC Address');
        }

        return $this->_validateFilter($mac, FILTER_VALIDATE_MAC, 'Invalid Mac Address');
    }

    /**
     * @param mixed $domain The domain we are testing its validity
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return bool|int
     * @throws InvalidDataException
     */
    public function asDomain(mixed $domain, ?string $regex = null): bool|int
    {
        if ($regex){
            return $this->validate($regex, $domain, 'Invalid domain');
        }

        return $this->_validateFilter($domain, FILTER_VALIDATE_DOMAIN, 'Invalid Domain');
    }


    /**
     * @param mixed $slug The slug string we are testing
     * @param string|null $regex Your custom regular expression we can depend on instead.
     *
     * @example ```
     *      $slug = 'fsjkfjshfsjk-skdhfkjdfsj-skdjfhjskdf'; // valid slug
     *      $slug2 = 'sfksdfsdskljfhsdhjkfhsdsfsdfsfsd'; // valid slug
     *      $slug3 = 'dkfl ksjfhsdk/skjdfsk%'; // invalid slug
     * @return bool|int
     * @throws InvalidDataException
     */
    public  function asSlug(mixed $slug, ?string $regex = null): bool|int
    {
        return $this->validate($regex ?? $this->slug_pattern, $slug, 'Invalid slug');
    }


    /**
     * Internal validator based on PHP filter_var validations
     * @param $value
     * @param $filterType
     * @param string $message
     * @return int|bool
     * @throws InvalidDataException
     */
    private function _validateFilter(mixed $value, $filterType, string $message = 'Invalid Data'): int | bool
    {
        $checker = filter_var($value, $filterType);
        if (!$checker && $this->throwsExceptions){
            throw new InvalidDataException($message);
        }
        return $checker == $value;
    }
}
