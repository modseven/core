<?php
/**
 * Modseven Encryption Engine Base class
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\Encrypt;

abstract class Engine
{
    /**
     * Encryption key
     * @var string
     */
    protected string $_key;

    /**
     * Cipher algorithm to use
     * @var string
     */
    protected ?string $_cipher = null;

    /**
     * Creates a new mcrypt wrapper.
     *
     * @param array  $key_config mcrypt key or config array
     * @param string $cipher     mcrypt cipher
     *
     * @throws Exception
     */
    public function __construct(array $key_config, ?string $cipher = null)
    {
        if (!isset($key_config['key']))
        {
            throw new Exception('No encryption key is defined in the encryption configuration');
        }

        $this->_key = $key_config['key'];

        if (isset($key_config['cipher']))
        {
            $this->_cipher = $key_config['cipher'];
        }
        elseif ($cipher !== null)
        {
            $this->_cipher = $cipher;
        }
    }

    /**
     * Encryption Function
     *
     * @param string $data  Data to encrypt
     * @param string $iv    Initialization Vector
     *
     * @return string|bool  False on Failure
     */
    abstract public function encrypt(string $data, string $iv);

    /**
     * Decryption Function
     *
     * @param string $crypto Encrypted Data String
     *
     * @return string|bool  False on failed decryption
     */
    abstract public function decrypt(string $crypto);

    /**
     * Initialization Vector generation
     *
     * @return string
     */
    abstract public function create_iv() : string;

}