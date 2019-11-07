<?php
/**
 * Cookie helper.
 *
 * @package    Modseven
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

class Cookie
{
    /**
     * Magic salt to add to the cookie
     * @var string
     */
    public static string $salt;

    /**
     * Number of seconds before the cookie expires
     * @var integer
     */
    public static int $expiration = 0;

    /**
     * Restrict the path that the cookie is available to
     * @var string
     */
    public static string $path = '/';

    /**
     * Restrict the domain that the cookie is available to
     * @var string
     */
    public static string $domain;

    /**
     * Only transmit cookies over secure connections
     * @var boolean
     */
    public static bool $secure = false;

    /**
     * Only transmit cookies over HTTP, disabling Javascript access
     * @var boolean
     */
    public static bool $httponly = false;

    /**
     * Gets the value of a signed cookie. Cookies without signatures will not
     * be returned. If the cookie signature is present, but invalid, the cookie
     * will be deleted.
     *
     * @param string $key cookie name
     * @param mixed $default default value to return
     *
     * @return null|string
     *
     * @throws Exception
     */
    public static function get(string $key, $default = NULL): ?string
    {
        if (!isset($_COOKIE[$key])) {
            // The cookie does not exist
            return $default;
        }

        // Get the cookie value
        $cookie = $_COOKIE[$key];

        // Find the position of the split between salt and contents
        $split = strlen(self::salt($key, NULL));

        if (isset($cookie[$split]) && $cookie[$split] === '~') {
            // Separate the salt and the value
            [$hash, $value] = explode('~', $cookie, 2);

            if (Security::slowEquals(self::salt($key, $value), $hash)) {
                // Cookie signature is valid
                return $value;
            }

            // The cookie signature is invalid, delete it
            self::delete($key);
        }

        return $default;
    }

    /**
     * Generates a salt string for a cookie based on the name and value.
     *
     * @param string      $name  name of cookie
     * @param null|string $value value of cookie
     *
     * @return  string
     *
     * @throws Exception if Cookie::$salt is not configured
     */
    public static function salt(string $name, ?string $value): string
    {
        // Require a valid salt
        if (!static::$salt) {
            throw new Exception('A valid cookie salt is required. Please set Cookie::$salt in your config/app.php. For more information check the documentation');
        }

        // Determine the user agent
        $agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : 'unknown';

        return hash_hmac('sha1', $agent . $name . $value . static::$salt, static::$salt);
    }

    /**
     * Deletes a cookie by making the value NULL and expiring it.
     *
     * @param string $name cookie name
     * @return  boolean
     */
    public static function delete(string $name): bool
    {
        // Remove the cookie
        unset($_COOKIE[$name]);

        // Nullify the cookie and make it expire
        return self::_setcookie($name, NULL, -86400, static::$path, static::$domain, static::$secure, static::$httponly);
    }

    /**
     * Proxy for the native setcookie function - to allow mocking in unit tests so that they do not fail when headers
     * have been sent.
     *
     * @param string $name
     * @param string $value
     * @param integer $expire
     * @param string $path
     * @param string $domain
     * @param boolean $secure
     * @param boolean $httponly
     *
     * @return bool
     */
    protected static function _setcookie(string $name, string $value, int $expire, string $path, string $domain, bool $secure, bool $httponly): bool
    {
        return setcookie($name, $value, $expire, $path, $domain, $secure, $httponly);
    }

    /**
     * Sets a signed cookie. Note that all cookie values must be strings and no
     * automatic serialization will be performed!
     *
     * [!!] By default, Cookie::$expiration is 0 - if you skip/pass NULL for the optional
     *      lifetime argument your cookies will expire immediately unless you have separately
     *      configured Cookie::$expiration.
     *
     * @param string $name name of cookie
     * @param string $value value of cookie
     * @param integer $lifetime lifetime in seconds
     *
     * @return  boolean
     *
     * @throws Exception
     */
    public static function set(string $name, string $value, ?int $lifetime = NULL): bool
    {
        if ($lifetime === NULL) {
            // Use the default expiration
            $lifetime = static::$expiration;
        }

        if ($lifetime !== 0) {
            // The expiration is expected to be a UNIX timestamp
            $lifetime += time();
        }

        // Add the salt to the cookie value
        $value = self::salt($name, $value) . '~' . $value;

        return self::_setcookie($name, $value, $lifetime, static::$path, static::$domain, static::$secure, static::$httponly);
    }

}
