<?php
/**
 * Security helper class.
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

use Exception;

class Security
{
    /**
     * key name used for token storage
     * @var string
     */
    public static string $token_name = 'security_token';

    /**
     * Check that the given token matches the currently stored security token.
     *
     * @param string $token token to check
     *
     * @return  boolean
     *
     * @throws \Modseven\Exception
     */
    public static function check(string $token): bool
    {
        return self::slowEquals(self::token(), $token);
    }

    /**
     * Compare two hashes in a time-invariant manner.
     * Prevents cryptographic side-channel attacks (timing attacks, specifically)
     *
     * @param string $a cryptographic hash
     * @param string $b cryptographic hash
     *
     * @return boolean
     */
    public static function slowEquals(string $a, string $b): bool
    {
        $diff = strlen($a) ^ strlen($b);
        for ($i = 0; $i < strlen($a) && $i < strlen($b); $i++) {
            $diff |= ord($a[$i]) ^ ord($b[$i]);
        }
        return $diff === 0;
    }

    /**
     * Generate and store a unique token which can be used to help prevent
     * [CSRF](http://wikipedia.org/wiki/Cross_Site_Request_Forgery) attacks.
     *
     *     $token = Security::token();
     *
     * You can insert this token into your forms as a hidden field:
     *
     *     echo Form::hidden('csrf', Security::token());
     *
     * And then check it when using [Validation]:
     *
     *     $array->rules('csrf', array(
     *         array('not_empty'),
     *         array('Security::check'),
     *     ));
     *
     * This provides a basic, but effective, method of preventing CSRF attacks.
     *
     * @param boolean $new force a new token to be generated?
     *
     * @return  string
     *
     * @throws \Modseven\Exception
     */
    public static function token(bool $new = FALSE): string
    {
        $session = Session::instance();

        // Get the current token
        $token = $session->get(static::$token_name);

        if ($new || !$token) {
            $token = self::_generateToken();

            // Store the new token
            $session->set(static::$token_name, $token);
        }

        return $token;
    }

    /**
     * Generate a unique token.
     *
     * @return  string
     */
    protected static function _generateToken(): string
    {
        if (function_exists('\random_bytes')) {
            try {
                return bin2hex(random_bytes(24));
            } catch (Exception $e) {
                // Random bytes function is available but no sources of randomness are available
                // so rather than allowing the exception to be thrown - fall back to other methods.
                // @see http://php.net/manual/en/function.random-bytes.php
            }
        }

        if (function_exists('\openssl_random_pseudo_bytes')) {
            // Generate a random pseudo bytes token if openssl_random_pseudo_bytes is available
            // This is more secure than uniqid, because uniqid relies on microtime, which is predictable
            return base64_encode(openssl_random_pseudo_bytes(32));
        }

        return sha1(uniqid(NULL, TRUE));
    }

    /**
     * Encodes PHP tags in a string.
     *
     * @param string $str string to sanitize
     * @return  string
     */
    public static function encodePhpTags(string $str): string
    {
        return str_replace(['<?', '?>'], ['&lt;?', '?&gt;'], $str);
    }

}
