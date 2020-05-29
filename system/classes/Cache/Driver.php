<?php

namespace Modseven\Cache;

abstract class Driver
{
    /**
     * Holds the cache drivers configuration
     * @var array
     */
    protected array $_config;

    /**
     * Holds if the lookup was a hit.
     * This is used for PSR Cache Item, we use this so drivers will not have to deal with this.
     * We can not just return false as this is a valid cache value
     *
     * @var bool
     */
    protected bool $isHit = false;

    /**
     * Driver constructor.
     * Set's the configuration variable
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->_config = $config;
    }

    /**
     * Confirms if the cache contains specified cache item.
     *
     * Note: This method MAY avoid retrieving the cached value for performance reasons.
     * This could result in a race condition with CacheItemInterface::get(). To avoid
     * such situation use CacheItemInterface::isHit() instead.
     *
     * @param string $key The key for which to check existence.
     *
     * @return bool True if item exists in the cache, false otherwise.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    abstract public function has(string $key) : bool;

    /**
     * @param string $key
     *
     */
    abstract public function get(string $key);

    /**
     * @param array $keys
     *
     * @return array
     */
    abstract public function getMultiple(array $keys) : array;

    /**
     * @param string $key
     * @param        $value
     * @param        $lifetime
     *
     * @return bool
     */
    abstract public function set(string $key, $value, ?int $lifetime) : bool;

    /**
     * @param array $items
     *
     *
     *                    [key] => [
     *                      'value' => <value>
     *                      'ttl' => <ttl>
     *                    ]
     * @return bool
     */
    abstract public function setMultiple(array $items) : bool;

    /**
     * @param string $key
     *
     * @return bool
     */
    abstract public function delete(string $key) : bool;

    /**
     * @param array $keys
     *
     * @return bool
     */
    abstract public function deleteMultiple(array $keys) : bool;

    /**
     * @return bool
     */
    abstract public function clear() : bool;

    /**
     * Overload the __clone() method to prevent cloning
     *
     * @throws  Exception
     */
    final public function __clone()
    {
        throw new Exception('Cloning of Cache objects is forbidden');
    }

    /**
     * Returns if the lookup was a hit
     * @return bool
     */
    final public function isHit() : bool
    {
        return $this->isHit;
    }
}