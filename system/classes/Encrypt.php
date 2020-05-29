<?php
/**
 * Modseven Encrypt class for symmetric encryption
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven;

use Modseven\Encrypt\Exception;
use Modseven\Encrypt\Engine;
use Modseven\Encrypt\Engine\OpenSSL;

class Encrypt
{
    /**
     * Default instance name
     * @var string
     */
    public static string $default = 'default';

    /**
     * Encrypt class instances
     * @var array
     */
    public static array $instances = [];

    /**
     * Encryption engine
     * @var Engine
     */
    public ?Engine $_engine = null;

    /**
     * Returns a singleton instance of Encrypt. An encryption key must be
     * provided in your "encrypt" configuration file.
     *
     * @param string $name   Configuration group name
     * @param array  $config Configuration
     *
     * @return Encrypt
     *
     * @throws Exception
     */
    public static function instance(?string $name = null, ?array $config = null) : Encrypt
    {
        if ($name === null)
        {
            // Use the default instance name
            $name = static::$default;
        }

        if ( ! isset(static::$instances[$name]))
        {
            if ($config === null)
            {
                // Load the configuration data
                try
                {
                    $config = \Modseven\Config::instance()->load('encrypt')->$name;
                }
                catch (\Modseven\Exception $e)
                {
                    throw new Exception($e->getMessage(), null, $e->getCode(), $e);
                }

            }

            // Create a new instance
            static::$instances[$name] = new Encrypt($config);
        }

        return static::$instances[$name];
    }

    /**
     * Creates a new mcrypt wrapper.
     *
     * @param array  $key_config Encryption config array
     * @param string $cipher     Encryption cipher
     */
    public function __construct(array $key_config, ?string $cipher = null)
    {
        // Determine the encryption driver
        $driver = $key_config['driver'] ?? OpenSSL::class;

        // Create the engine class
        $this->_engine = new $driver($key_config, $cipher);
    }

    /**
     * Encrypts a string and returns an encrypted string that can be decoded.
     *
     * The encrypted binary data is encoded using [base64](http://php.net/base64_encode)
     * to convert it to a string. This string can be stored in a database,
     * displayed, and passed using most other means without corruption.
     *
     * @param string $data Data to be encrypted
     *
     * @return string
     */
    public function encode(string $data) : string
    {
        // Get an initialization vector
        $iv = $this->_engine->createIv();

        return $this->_engine->encrypt($data, $iv);
    }

    /**
     * Decrypts an encoded string back to its original value.
     *
     * @param string $crpto Encoded string to be decrypted
     *
     * @return string|bool  False if decryption fails
     */
    public function decode(string $crpto)
    {
        return $this->_engine->decrypt($crpto);
    }

}