<?php
namespace  Pionia\request;

use Pionia\exceptions\InvalidDataException;

trait ValidationTrait
{
    private  string $email_pattern = "/^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$/";
    private  string $phone_pattern = "/^(0)[1-9]\d{9}$/";
    private  string $password_pattern = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*(_|[^\w])).+$/";
    private  string $username_pattern = "/^[a-zA-Z0-9_]{5,}$/";
    private  string $date_pattern = "/^\d{4}-\d{2}-\d{2}$/";
    private  string $time_pattern = "/^\d{2}:\d{2}$/";
    private  string $datetime_pattern = "/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/";
    private  string $ip_pattern = "/^(\d{1,3}\.){3}\d{1,3}$/";
    private  string $slug_pattern = "/^[a-z0-9-]+$/";
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

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidDataException('Invalid email address');
        }
        return $this->_validateFilter($email, FILTER_VALIDATE_EMAIL, 'Invalid Email Address');
    }

    /**
     * @throws InvalidDataException
     */
    private function _validate_phone(string $number, ?string $code, ?string $regex = null): bool
    {
//        // if the user provided the regex, don't bother doing anything
        if ($regex  || !$code){
            return $this->validate($regex ?? $this->phone_pattern, $number);
        }

        // make the copy of the phone number
        $copy = $number;
        if (!str_starts_with($copy, $code)){
            throw new InvalidDataException('Invalid phone number, must start with '.$code);
        }
        $str = str_replace($code, '0',  $number);
        return $this->validate($this->phone_pattern, $str, 'Invalid phone number');
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
    public  function asPhone(string $phone, ?string $code = null, ?string $regex = null): bool|int
    {
        return $this->_validate_phone($phone, $code, $regex);
    }

    /**
     * Validates the rules as follows
     *  - Atleast one integer
     *  - Atleast one lowercase alpha letter
     *  - Atleast one Uppercase alpha letter
     *  - Atleast one special character
     *
     * @param mixed $password The password string we are testing, must be raw, not hashed
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @throws InvalidDataException
     */
    public  function asPassword($password, ?string $regex = null): bool|int
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
    public  function asUsername($username, ?string $regex = null): bool|int
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
    public  function asUrl($url, ?string $regex = null): bool|int
    {
        if ($regex){
            return $this->validate($url, $regex, 'Invalid URL');
        }

        return $this->_validateFilter($url, FILTER_VALIDATE_URL, 'Invalid Url');
    }

    /**
     * @param mixed $ip The Ip address we checking.
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
     * @return bool|int|mixed
     * @throws InvalidDataException
     */
    public function asDomain(mixed $domain, ?string $regex = null)
    {
        if ($regex){
            return $this->validate($regex, $domain, 'Invalid domain');
        }

        return $this->_validateFilter($domain, FILTER_VALIDATE_DOMAIN, 'Invalid Domain');
    }


    /**
     * @param mixed $slug The slug string we are testing
     * @param string|null $regex Your custom regular expression we can depend on instead.
     * @return bool|int
     * @throws InvalidDataException
     */
    public  function asSlug($slug, ?string $regex = null): bool|int
    {
        return $this->validate($regex ?? $this->slug_pattern, $slug, 'Invalid slug');
    }


    /**
     * Internal validator based on PHP filter_var validations
     * @param $value
     * @param $filterType
     * @param $meesage
     * @return mixed
     * @throws InvalidDataException
     */
    private function _validateFilter($value, $filterType, $meesage = 'Invalid Data'): int | bool
    {
        $checker = filter_var($value, $filterType);
        if (!$checker && $this->throwsExceptions){
            throw new InvalidDataException($meesage);
        }
        return $checker;
    }
}
