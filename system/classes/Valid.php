<?php
/**
 * Validation rules.
 *
 * @package    Modseven
 * @category   Security
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

use ArrayObject;

class Valid
{
    /**
     * Checks a field against a regular expression.
     *
     * @param string|null $value value
     * @param string $expression regular expression to match (including delimiters)
     * @return  boolean
     */
    public static function regex(?string $value, string $expression): bool
    {
        return (bool)preg_match($expression, $value);
    }

    /**
     * Checks that a field is long enough.
     *
     * @param string|null $value value
     * @param integer $length minimum length required
     * @return  boolean
     */
    public static function minLength(?string $value, int $length): bool
    {
        return UTF8::strlen((string)$value) >= $length;
    }

    /**
     * Checks that a field is short enough.
     *
     * @param string|null $value value
     * @param integer $length maximum length required
     * @return  boolean
     */
    public static function maxLength(?string $value, int $length): bool
    {
        return UTF8::strlen((string)$value) <= $length;
    }

    /**
     * Checks that a field is exactly the right length.
     *
     * @param string|null $value value
     * @param integer|array $length exact length required, or array of valid lengths
     * @return  boolean
     */
    public static function exactLength(?string $value, $length): bool
    {
        $value = (string)$value;
        if (is_array($length)) {
            foreach ($length as $strlen) {
                if (UTF8::strlen($value) === $strlen) {
                    return true;
                }
            }
            return FALSE;
        }

        return UTF8::strlen($value) === $length;
    }

    /**
     * Checks that a field is exactly the value required.
     *
     * @param string|null $value value
     * @param string $required required value
     * @return  boolean
     */
    public static function equals(?string $value, string $required): bool
    {
        return ($value === $required);
    }

    /**
     * Validates e-mail address
     * @link  http://www.iamcal.com/publish/articles/php/parsing_email/
     * @link  http://www.w3.org/Protocols/rfc822/
     *
     * @param string|null $email e-mail address
     * @param bool $strict strict e-mail checking
     * @return boolean
     */
    public static function email(?string $email, bool $strict = FALSE): bool
    {
        if ($strict) {
            return filter_var(filter_var($email, FILTER_SANITIZE_STRING), FILTER_VALIDATE_EMAIL) !== FALSE;
        }
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== FALSE;
    }

    /**
     * Validate the domain of an email address by checking if the domain has a
     * valid MX record.
     *
     * @link  http://php.net/checkdnsrr  not added to Windows until PHP 5.3.0
     *
     * @param string|null $email email address
     * @return  boolean
     */
    public static function emailDomain(?string $email): bool
    {
        // Empty fields cause issues with checkdnsrr()
        if (!self::notEmpty($email)) {
            return false;
        }

        // Check if the email domain has a valid MX record
        return (bool)checkdnsrr(preg_replace('/^[^@]++@/', '', $email), 'MX');
    }

    /**
     * Checks if a field is not empty.
     *
     * @param mixed $value Object/array to check
     *
     * @return  boolean
     */
    public static function notEmpty($value): bool
    {
        if (is_object($value) && $value instanceof ArrayObject) {
            // Get the array from the ArrayObject
            $value = $value->getArrayCopy();
        }

        // Value cannot be NULL, FALSE, '', or an empty array
        return !in_array($value, [NULL, FALSE, '', []], TRUE);
    }

    /**
     * Validate a URL.
     *
     * @param string|null $url URL
     * @return  boolean
     */
    public static function url(?string $url): bool
    {
        // Based on http://www.apps.ietf.org/rfc/rfc1738.html#sec-5
        if (!preg_match(
            '~^

			# scheme
			[-a-z0-9+.]++://

			# username:password (optional)
			(?:
				    [-a-z0-9$_.+!*\'(),;?&=%]++   # username
				(?::[-a-z0-9$_.+!*\'(),;?&=%]++)? # password (optional)
				@
			)?

			(?:
				# ip address
				\d{1,3}+(?:\.\d{1,3}+){3}+

				| # or

				# hostname (captured)
				(
					     (?!-)[-a-z0-9]{1,63}+(?<!-)
					(?:\.(?!-)[-a-z0-9]{1,63}+(?<!-)){0,126}+
				)
			)

			# port (optional)
			(?::\d{1,5}+)?

			# path (optional)
			(?:/.*)?

			$~iDx', $url, $matches)) {
            return false;
        }

        // We matched an IP address
        if (!isset($matches[1])) {
            return true;
        }

        // Check maximum length of the whole hostname
        // http://en.wikipedia.org/wiki/Domain_name#cite_note-0
        if (strlen($matches[1]) > 253) {
            return false;
        }

        // An extra check for the top level domain
        // It must start with a letter
        $tld = ltrim(substr($matches[1], (int)strrpos($matches[1], '.')), '.');
        return ctype_alpha($tld[0]);
    }

    /**
     * Validate an IP.
     *
     * @param string|null $ip IP address
     * @param boolean $allow_private allow private IP networks
     * @return  boolean
     */
    public static function ip(?string $ip, bool $allow_private = TRUE): bool
    {
        // Do not allow reserved addresses
        $flags = FILTER_FLAG_NO_RES_RANGE;

        if ($allow_private === FALSE) {
            // Do not allow private or reserved addresses
            $flags |= FILTER_FLAG_NO_PRIV_RANGE;
        }

        return (bool)filter_var($ip, FILTER_VALIDATE_IP, $flags);
    }

    /**
     * Validates a credit card number, with a Luhn check if possible.
     *
     * @param int|null $number credit card number
     * @param string|array $type card type, or an array of card types
     *
     * @return  boolean
     *
     * @throws Exception
     */
    public static function creditCard(?int $number, $type = NULL): bool
    {
        // Remove all non-digit characters from the number
        if (($number = preg_replace('/\D+/', '', $number)) === '') {
            return false;
        }

        if ($type === NULL) {
            // Use the default type
            $type = 'default';
        } elseif (is_array($type)) {
            foreach ($type as $t) {
                // Test each type for validity
                if (self::creditCard($number, $t)) {
                    return true;
                }
            }

            return FALSE;
        }

        $cards = \Modseven\Config::instance()->load('credit_cards');

        // Check card type
        $type = strtolower($type);

        if (!isset($cards[$type])) {
            return false;
        }

        // Check card number length
        $length = strlen($number);

        // Validate the card length by the card type
        if (!in_array($length, preg_split('/\D+/', $cards[$type]['length']), true)) {
            return false;
        }

        // Check card number prefix
        if (!preg_match('/^' . $cards[$type]['prefix'] . '/', $number)) {
            return false;
        }

        // No Luhn check required
        if ($cards[$type]['luhn'] === FALSE) {
            return true;
        }

        return self::luhn($number);
    }

    /**
     * Validate a number against the [Luhn](http://en.wikipedia.org/wiki/Luhn_algorithm)
     * (mod10) formula.
     *
     * @param string $number number to check
     * @return  boolean
     */
    public static function luhn(string $number): bool
    {
        if (!ctype_digit($number)) {
            // Luhn can only be used on numbers!
            return FALSE;
        }

        // Check number length
        $length = strlen($number);

        // Checksum of the card number
        $checksum = 0;

        for ($i = $length - 1; $i >= 0; $i -= 2) {
            // Add up every 2nd digit, starting from the right
            $checksum += $number[$i];
        }

        for ($i = $length - 2; $i >= 0; $i -= 2) {
            // Add up every 2nd digit doubled, starting from the right
            $double = $number[$i] * 2;

            // Subtract 9 from the double where value is greater than 10
            $checksum += ($double >= 10) ? ($double - 9) : $double;
        }

        // If the checksum is a multiple of 10, the number is valid
        return ($checksum % 10 === 0);
    }

    /**
     * Checks if a phone number is valid.
     *
     * @param string|null $number phone number to check
     * @param array|null $lengths
     * @return  boolean
     */
    public static function phone(?string $number, ?array $lengths = NULL): bool
    {
        if (!is_array($lengths)) {
            $lengths = [7, 10, 11];
        }

        // Remove all non-digit characters from the number
        $number = preg_replace('/\D+/', '', $number);

        // Check if the number is within range
        return in_array(strlen($number), $lengths, true);
    }

    /**
     * Tests if a string is a valid date string.
     *
     * @param string $str date to check
     * @return  boolean
     */
    public static function date(string $str): bool
    {
        return (strtotime($str) !== FALSE);
    }

    /**
     * Checks whether a string consists of alphabetical characters only.
     *
     * @param string $str input string
     * @param boolean $utf8 trigger UTF-8 compatibility
     * @return  boolean
     */
    public static function alpha(string $str, bool $utf8 = FALSE): bool
    {
        if ($utf8 === TRUE) {
            return (bool)preg_match('/^\pL++$/uD', $str);
        }

        return ctype_alpha($str);
    }

    /**
     * Checks whether a string consists of alphabetical characters and numbers only.
     *
     * @param string $str input string
     * @param boolean $utf8 trigger UTF-8 compatibility
     * @return  boolean
     */
    public static function alphaNumeric(string $str, bool $utf8 = FALSE): bool
    {
        if ($utf8 === TRUE) {
            return (bool)preg_match('/^[\pL\pN]++$/uD', $str);
        }

        return ctype_alnum($str);
    }

    /**
     * Checks whether a string consists of alphabetical characters, numbers, underscores and dashes only.
     *
     * @param string $str input string
     * @param boolean $utf8 trigger UTF-8 compatibility
     * @return  boolean
     */
    public static function alphaDash(string $str, bool $utf8 = FALSE): bool
    {
        if ($utf8 === TRUE) {
            $regex = '/^[-\pL\pN_]++$/uD';
        } else {
            $regex = '/^[-a-z0-9_]++$/iD';
        }

        return (bool)preg_match($regex, $str);
    }

    /**
     * Checks whether a string consists of digits only (no dots or dashes).
     *
     * @param string $str input string
     * @param boolean $utf8 trigger UTF-8 compatibility
     * @return  boolean
     */
    public static function digit(string $str, bool $utf8 = FALSE): bool
    {
        if ($utf8 === TRUE) {
            return (bool)preg_match('/^\pN++$/uD', $str);
        }

        return (is_int($str) AND $str >= 0) OR ctype_digit($str);
    }

    /**
     * Checks whether a string is a valid number (negative and decimal numbers allowed).
     *
     * Uses {@link http://www.php.net/manual/en/function.localeconv.php locale conversion}
     * to allow decimal point to be locale specific.
     *
     * @param string $str input string
     * @return  boolean
     */
    public static function numeric(string $str): bool
    {
        // Get the decimal point for the current locale
        [$decimal] = array_values(localeconv());

        // A lookahead is used to make sure the string contains at least one digit (before or after the decimal point)
        return (bool)preg_match('/^-?+(?=.*[0-9])[0-9]*+' . preg_quote($decimal, '/') . '?+[0-9]*+$/D', $str);
    }

    /**
     * Tests if a number is within a range.
     *
     * @param string|null $number number to check
     * @param integer $min minimum value
     * @param integer $max maximum value
     * @param int|null $step increment size
     * @return  boolean
     */
    public static function range(?string $number, int $min, int $max, int $step = NULL): bool
    {
        if ($number < $min || $number > $max) {
            // Number is outside of range
            return FALSE;
        }

        if (!$step) {
            // Default to steps of 1
            $step = 1;
        }

        // Check step requirements
        return (($number - $min) % $step === 0);
    }

    /**
     * Checks if a string is a proper decimal format. Optionally, a specific
     * number of digits can be checked too.
     *
     * @param string|null $str number to check
     * @param integer $places number of decimal places
     * @param int|null $digits number of digits
     * @return  boolean
     */
    public static function decimal(?string $str, int $places = 2, int $digits = NULL): bool
    {
        if ($digits > 0) {
            // Specific number of digits
            $digits = '{' . ($digits) . '}';
        } else {
            // Any number of digits
            $digits = '+';
        }

        // Get the decimal point for the current locale
        [$decimal] = array_values(localeconv());

        return (bool)preg_match('/^[+-]?[0-9]' . $digits . preg_quote($decimal, null) . '[0-9]{' . ($places) . '}$/D', $str);
    }

    /**
     * Checks if a string is a proper hexadecimal HTML color value. The validation
     * is quite flexible as it does not require an initial "#" and also allows for
     * the short notation using only three instead of six hexadecimal characters.
     *
     * @param string $str input string
     * @return  boolean
     */
    public static function color(string $str): bool
    {
        return (bool)preg_match('/^#?+[0-9a-f]{3}(?:[0-9a-f]{3})?$/iD', $str);
    }

    /**
     * Checks if a field matches the value of another field.
     *
     * @param array $array array of values
     * @param string $field field name
     * @param string $match field name to match
     * @return  boolean
     */
    public static function matches(array $array, string $field, string $match): bool
    {
        return ($array[$field] === $array[$match]);
    }

}
