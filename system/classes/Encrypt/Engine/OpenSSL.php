<?php
/**
 * The Encrypt Openssl engine provides two-way encryption of text and binary strings
 * using the [OpenSSL](http://php.net/openssl) extension, which consists of two
 * parts: the key and the cipher.
 *
 * The Key
 * :  A secret passphrase that is used for encoding and decoding
 *
 * The Cipher
 * :  A [cipher](http://php.net/manual/en/openssl.ciphers.php) determines how the encryption
 *    is mathematically calculated.
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\Encrypt\Engine;

use Modseven\Encrypt\Engine;
use Modseven\Encrypt\Exception;

use function openssl_decrypt;
use function openssl_encrypt;

class OpenSSL extends Engine
{
    /**
     * The size of the Initialization Vector (IV) in bytes
     * @var int
     */
    protected int $_iv_size;

    /**
     * Creates a new openssl wrapper.
     *
     * @param array  $key_config  encryption config
     * @param string $cipher      openssl cipher
     *
     * @throws Exception
     */
    public function __construct(array $key_config, ?string $cipher = null)
    {
        // Call the parent constructor
        parent::__construct($key_config, $cipher);

        // Make sure that we have a cipher
        if ($this->_cipher === null)
        {
            throw new Exception('OpenSSL Encryption needs a cipher. Please provide one in your configuration.');
        }

        // Set the iv_size, this differs depending on the cipher
        $this->_iv_size = openssl_cipher_iv_length($this->_cipher);

        // Get the length of the key, we need to check this because different ciphers have different key lengths
        $length = mb_strlen($this->_key, '8bit');

        // Validate configuration
        if ($this->_cipher === 'AES-128-CBC')
        {
            if ($length !== 16)
            {
                // No valid encryption key is provided!
                throw new Exception('No valid encryption key is defined in the encryption configuration: length should be 16 for AES-128-CBC');
            }
        }
        elseif ($this->_cipher === 'AES-256-CBC')
        {
            if ($length !== 32)
            {
                // No valid encryption key is provided!
                throw new Exception('No valid encryption key is defined in the encryption configuration: length should be 32 for AES-256-CBC');
            }
        }
        else
        {
            // No valid encryption cipher is provided!
            throw new Exception('No valid encryption cipher is defined in the encryption configuration or the cipher is not supported by Modseven yet.');
        }
    }

    /**
     * Encrypts a string and returns an encrypted string that can be decoded.
     *
     * @param string $data  Data to encrypt
     * @param string $iv    Initialization Vector
     *
     * @return string|bool  False on Failure
     *
     * @throws Exception
     */
    public function encrypt(string $data, string $iv)
    {
        // First we will encrypt the value using OpenSSL. After this is encrypted we
        // will proceed to calculating a MAC for the encrypted value so that this
        // value can be verified later as not having been changed by the users.
        $value = openssl_encrypt($data, $this->_cipher, $this->_key, 0, $iv);

        if ($value === false)
        {
            // Encryption failed
            return false;
        }

        // Once we have the encrypted value we will go ahead base64_encode the input
        // vector and create the MAC for the encrypted value so we can verify its
        // authenticity. Then, we'll JSON encode the data in a "payload" array.
        $mac = $this->hash($iv = base64_encode($iv), $value);

        try
        {
            $json = json_encode(compact('iv', 'value', 'mac'), JSON_THROW_ON_ERROR);
        }
        catch (\Exception $e)
        {
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }
        if ( ! is_string($json))
        {
            // Encryption failed
            return false;
        }

        return base64_encode($json);
    }

    /**
     * Decrypts an encoded string back to its original value.
     *
     * @param string $data encoded string to be decrypted
     *
     * @return  string|bool   False if decryption fails
     *
     * @throws Exception
     */
    public function decrypt($data)
    {
        // Convert the data back to binary
        try
        {
            $data = json_decode(base64_decode($data), true, 512, JSON_THROW_ON_ERROR);
        }
        catch (\Exception $e)
        {
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }

        // If the payload is not valid JSON or does not have the proper keys set we will
        // assume it is invalid and bail out of the routine since we will not be able
        // to decrypt the given value. We'll also check the MAC for this encryption.
        if ( ! $this->validPayload($data))
        {
            // Decryption failed
            return false;
        }

        if ( ! $this->validMac($data))
        {
            // Decryption failed
            return false;
        }

        $iv = base64_decode($data['iv']);
        if ( ! $iv)
        {
            // Invalid base64 data
            return false;
        }

        // Here we will decrypt the value.
        return openssl_decrypt($data['value'], $this->_cipher, $this->_key, 0, $iv);
    }

    /**
     * Create a MAC for the given value.
     *
     * @param string $iv      Initialization Vector
     * @param string $value   Data to hash
     *
     * @return string
     */
    protected function hash(string $iv, string $value) : string
    {
        return hash_hmac('sha256', $iv . $value, $this->_key);
    }

    /**
     * Verify that the encryption payload is valid.
     *
     * @param array $payload
     *
     * @return bool
     */
    protected function validPayload(array $payload) : bool
    {
        return isset($payload['iv'], $payload['value'], $payload['mac']) && strlen(base64_decode($payload['iv'], true)) === $this->_iv_size;
    }

    /**
     * Determine if the MAC for the given payload is valid.
     *
     * @param array $payload
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function validMac(array $payload) : bool
    {
        $bytes = $this->createIv();

        $calculated = hash_hmac('sha256', $this->hash($payload['iv'], $payload['value']), $bytes, true);

        return hash_equals(hash_hmac('sha256', $payload['mac'], $bytes, true), $calculated);
    }

    /**
     * Proxy for the random_bytes function - to allow mocking and testing against KAT vectors
     *
     * @return string The initialization vector
     *
     * @throws Exception
     */
    public function createIv() : string
    {
        try
        {
            return random_bytes($this->_iv_size);
        }
        catch (\Exception $e)
        {
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }
    }

}