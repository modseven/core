<?php
/**
 * Modseven Cache provides a common interface to a variety of caching engines. Tags are
 * supported where available natively to the cache system. Modseven Cache supports multiple
 * instances of cache engines through a grouped singleton pattern.
 *
 * Note: This class is [PSR-6] and [PSR-16] compatible
 *
 * ### Supported cache engines
 * *  [Memcached](https://www.php.net/manual/de/book.memcached.php)
 * *  [SQLite](http://www.sqlite.org/)
 * *  [Redis](https://redislabs.com/lp/php-redis/)
 * *  File
 *
 * ### Introduction to caching
 * Caching should be implemented with consideration. Generally, caching the result of resources
 * is faster than reprocessing them. Choosing what, how and when to cache is vital. Redis is
 * presently one of the fastest caching systems available, closely followed by Memcache. SQLite
 * and File caching are two of the slowest cache methods, however usually faster than reprocessing
 * a complex set of instructions.
 * Caching engines that use memory are considerably faster than the file based alternatives. But
 * memory is limited whereas disk space is plentiful. If caching large datasets it is best to use
 * file caching.
 *
 * ### Configuration settings
 * Modseven Cache uses configuration groups to create cache instances. A configuration group can
 * use any supported driver, with successive groups using the same driver type if required.
 * In cases where only one cache group is required, set the default cache in your configuration
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven;

use Modseven\Cache\Item;
use Modseven\Cache\Driver;
use Modseven\Cache\Exception;
use Modseven\Cache\Driver\Tagging;
use Modseven\Cache\Driver\GarbageCollect;
use Modseven\Cache\InvalidArgumentException;

use Psr\Cache\CacheItemInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\Cache\CacheItemPoolInterface;

class Cache implements CacheItemPoolInterface
{
    /**
     * Cache instances
     * @var array
     */
    public static array $instances = [];

    /**
     * The current driver
     * @var Driver
     */
    protected Driver $_driver;

    /**
     * Holds deferred cache items
     * @var array
     */
    protected array $_deferred = [];

    /**
     * Ensures singleton pattern is observed, loads the default expiry
     *
     * @param Driver $driver Cache Driver Instance
     */
    protected function __construct(Driver $driver)
    {
        $this->_driver = $driver;
    }

    /**
     * Creates a singleton of a Modseven Cache group. If no group is supplied
     * the __default__ cache group is used.
     *
     * @param string $group the name of the cache group to use [Optional]
     *
     * @return  Cache
     * @throws  Exception
     */
    public static function instance(?string $group = null) : Cache
    {
        try
        {
            $config = \Modseven\Config::instance()->load('cache');
        }
        catch (\Modseven\Exception $e)
        {
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }

        if ($config === null)
        {
            throw new Exception('Cache configuration could not be found.');
        }


        if ($group === null)
        {
            if (!$config->offsetExists('default'))
            {
                throw new Exception('NO cache driver found. Please provide one either via configuration or parameter.');
            }

            // If there is no group supplied, try to get it from the config
            $group = $config->get('default');
        }

        // Check if the configuration for this group exists
        if (!$config->offsetExists($group))
        {
            throw new Exception('Failed to load Configuration of Cache group: :group', [':group' => $group]);
        }

        // Return the current instance if initiated already
        if (isset(static::$instances[$group]))
        {
            return static::$instances[$group];
        }

        // Create a new cache type instance
        $config = $config->get($group);

        // Instance the driver
        $driver = $config['driver'];
        $driver = new $driver($config);

        // Make sure it extends the Driver Class
        if (!$driver instanceof Driver)
        {
            throw new Exception('All Cache drivers must extend the "Driver\\Driver" Class');
        }

        // Instance this class
        static::$instances[$group] = new Cache($driver);

        // Return the instance
        return static::$instances[$group];
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
    public function hasItem($key) : bool
    {
        if (!is_scalar($key) && (is_object($key) && !method_exists($key, '__toString')))
        {
            throw new InvalidArgumentException('Cache key must be a string or Object which can be converted to string.');
        }

        return $this->_driver->has($this->_sanitizeId((string)$key));
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * This method must always return a CacheItemInterface object, even in case of
     * a cache miss. It MUST NOT return null.
     *
     * @param string $key The key for which to return the corresponding Cache Item.
     *
     * @return Item The corresponding Cache Item.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getItem($key) : Item
    {
        if (!is_scalar($key) && (is_object($key) && !method_exists($key, '__toString')))
        {
            throw new InvalidArgumentException('Cache key must be a string or Object which can be converted to string.');
        }

        // Initialize the Cache Item
        $item = new Item();
        $item->setKey($key);
        $item->set($this->_driver->get($this->_sanitizeId((string)$key)));

        return $item;
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param string[] $keys An indexed array of keys of items to retrieve.
     *
     * @return array|\Traversable
     *   A traversable collection of Cache Items keyed by the cache keys of
     *   each item. A Cache item will be returned for each key, even if that
     *   key is not found. However, if no keys are specified then an empty
     *   traversable MUST be returned instead.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function getItems(array $keys = []) : array
    {
        if (empty($keys))
        {
            return [];
        }

        $sanitizedIds = [];
        foreach ($keys as $key)
        {
            if (!is_scalar($key) && (is_object($key) && !method_exists($key, '__toString')))
            {
                throw new InvalidArgumentException('Cache key must be a string or Object which can be converted to string.');
            }

            $sanitizedIds[$key] = $this->_sanitizeId((string)$key);
        }

        $items = [];

        // Get the cache values and create the item objects with corresponding keys
        foreach ($this->_driver->getMultiple($sanitizedIds) as $cacheKey => $cacheValue)
        {
            $item = new Item();
            $item->set($cacheValue);
            $item->setKey(array_search($cacheKey, $sanitizedIds, true));
            $item->setSanitizedKey($cacheKey);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Find cache entries based on a tag
     *
     * @param   string  $tag  tag
     *
     * @return  array
     *
     * @throws Exception
     */
    public function getItemsWithTag(string $tag) : array
    {
        if (!$this->_driver instanceof Tagging)
        {
            throw new Exception('This Cache Driver does not support tagging');
        }

        $items = [];
        foreach ($this->_driver->getWithTag($tag) as $cacheKey => $cacheValue)
        {
            $item = new Item();
            $item->setKey($cacheKey);
            $item->set($cacheValue);
            $items[] = $item;
        }

        return $items;
    }

    /**
     * PSR-16 version for getItems
     *
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @return array A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function getMultiple($keys, $default = null) : array
    {
        if (!is_iterable($keys))
        {
            throw new InvalidArgumentException('Keys supplied to getMultiple must be iterable in order to work');
        }

        $sanitizedKeys = [];
        foreach ($keys as $key)
        {
            if (!is_scalar($key) && (is_object($key) && !method_exists($key, '__toString')))
            {
                throw new InvalidArgumentException('Cache key must be a string or Object which can be converted to string.');
            }

            $sanitizedKeys[$key] = $this->_sanitizeId($key);
        }

        $cacheItems = $this->_driver->getMultiple($sanitizedKeys);

        $results = [];
        foreach ($cacheItems as $sanitizedKey => $result)
        {
            if ($result === false)
            {
                $result = $default;
            }

            $results[array_search($sanitizedKey, $sanitizedKeys, true)] = $result;
        }

        return $results;
    }

    /**
     * Find cache entries based on a tag
     *
     * @param   string  $tag  tag
     *
     * @return  array
     *
     * @throws Exception
     */
    public function getWithTag(string $tag) : array
    {
        if (!$this->_driver instanceof Tagging)
        {
            throw new Exception('This Cache Driver does not support tagging');
        }

        return $this->_driver->getWithTag($tag);
    }

    /**
     * Persists a cache item immediately.
     *
     * @param CacheItemInterface $item The cache item to save.
     *
     * @return bool True if the item was successfully persisted. False if there was an error.
     *
     * @throws Exception
     */
    public function save(CacheItemInterface $item) : bool
    {
        if (!$item instanceof Item)
        {
            throw new Exception('Modseven requires Cache Items to be an instance of "Modseven\\Cache\\Item"');
        }

        if ($item->getSanitizedKey() === null)
        {
            $item->setSanitizedKey($this->_sanitizeId($item->getKey()));
        }

        return $this->_driver->set($item->getSanitizedKey(), $item->get(), $item->getLifetime());
    }

    /**
     * Set a value based on an id. Optionally add tags.
     *
     * Note : Some caching engines do not support
     * tagging, also the keys are not sanitized as we cannot reverse those
     *
     * @param CacheItemInterface $item The cache item to save.
     *
     * @return  bool
     *
     * @throws Exception
     */
    public function saveWithTags(CacheItemInterface $item) : bool
    {
        if (!$item instanceof Item)
        {
            throw new Exception('Modseven requires Cache Items to be an instance of "Modseven\\Cache\\Item"');
        }

        if (!$this->_driver instanceof Tagging)
        {
            throw new Exception('This Cache Driver does not support tagging');
        }

        return $this->_driver->setWithTags($item->getKey(), $item->get(), $item->getTags(), $item->getLifeTime());
    }

    /**
     * PSR-16 version of "save"
     *
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store. Must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function set($key, $value, $ttl = null) : bool
    {
        if (!is_scalar($key) && (is_object($key) && !method_exists($key, '__toString')))
        {
            throw new InvalidArgumentException('Cache key must be a string or Object which can be converted to string.');
        }

        return $this->_driver->set($this->_sanitizeId($key), $value, $ttl);
    }

    /**
     * Set a value based on an id. Optionally add tags.
     *
     * Note : Some caching engines do not support
     * tagging, also the keys are not sanitized as we cannot reverse those
     *
     * @param   string   $key       Cache key
     * @param   mixed    $data      data
     * @param   integer  $lifetime  lifetime [Optional]
     * @param   array    $tags      tags [Optional]
     *
     * @return  bool
     *
     * @throws Exception
     */
    public function setWithTags(string $key, $data, array $tags, ?int $lifetime = NULL) : bool
    {
        if (!$this->_driver instanceof Tagging)
        {
            throw new Exception('This Cache Driver does not support tagging');
        }

        return $this->_driver->setWithTags($key, $data, $tags, $lifetime);
    }

    /**
     * Persists multiple cache items
     *
     * @param array $cacheItems An array of CacheItemInterfaces to save.
     *
     * @return bool True if all items were successfully persisted. False if there was an error.
     */
    public function saveMultiple(array $cacheItems) : bool
    {
        $items = [];
        foreach ($cacheItems as $item)
        {
            $items[$item->getSanitizedKey()] = [
                'value' => $item->get(),
                'lifetime' => $item->getLifeTime()
            ];
        }

        return $this->_driver->setMultiple($items);
    }

    /**
     * PSR-16 compatible version of saveMultiple
     *
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function setMultiple($values, $ttl) : bool
    {
        $items = [];

        foreach ($values as $key => $value)
        {
            if (!is_scalar($key) && (is_object($key) && !method_exists($key, '__toString')))
            {
                throw new InvalidArgumentException('Cache key must be a string or Object which can be converted to string.');
            }

            $items[$this->_sanitizeId($key)] = [
                'value' => $value,
                'lifetime' => $ttl
            ];
        }

        return $this->_driver->setMultiple($items);
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @param CacheItemInterface $item The cache item to save.
     *
     * @return bool False if the item could not be queued or if a commit was attempted and failed. True otherwise.
     *
     * @throws Exception
     */
    public function saveDeferred(CacheItemInterface $item) : bool
    {
        if (!$item instanceof Item)
        {
            throw new Exception('Modseven requires Cache Items to be an instance of "Modseven\\Cache\\Item"');
        }

        if ($item->getSanitizedKey() === null)
        {
            $item->setSanitizedKey($this->_sanitizeId($item->getKey()));
        }

        $this->_deferred[] = $item;

        // We queue it internally so this always returns true
        return true;
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool True if all not-yet-saved items were successfully saved or there were none. False otherwise.
     */
    public function commit() : bool
    {
        if (empty($this->_deferred))
        {
            return true;
        }

        $items = [];
        foreach ($this->_deferred as $item)
        {
            $items[$item->getSanitizedKey()] = [
                'value' => $item->get(),
                'lifetime' => $item->getLifeTime()
            ];
        }

        return $this->_driver->setMultiple($items);
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key The key to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function deleteItem($key) : bool
    {
        if (!is_scalar($key) && (is_object($key) && !method_exists($key, '__toString')))
        {
            throw new InvalidArgumentException('Cache key must be a string or Object which can be converted to string.');
        }

        return $this->_driver->delete($this->_sanitizeId((string)$key));
    }

    /**
     * PSR-16 wrapper for "deleteItem"
     *
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function delete($key) : bool
    {
        return $this->deleteItem($key);
    }

    /**
     * Delete cache entries based on a tag
     *
     * @param   string  $tag  tag
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteTag(string $tag) : bool
    {
        if (!$this->_driver instanceof Tagging)
        {
            throw new Exception('This cache driver does not support tagging.');
        }

        return $this->_driver->deleteTag($tag);
    }

    /**
     * Removes multiple items from the pool.
     *
     * @param string[] $keys An array of keys that should be removed from the pool.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function deleteItems(array $keys) : bool
    {
        $sanitizedIds = [];
        foreach ($keys as $key)
        {
            if (!is_scalar($key) && (is_object($key) && !method_exists($key, '__toString')))
            {
                throw new InvalidArgumentException('Cache key must be a string or Object which can be converted to string.');
            }

            $sanitizedIds[] = $this->_sanitizeId((string)$key);
        }

        return $this->_driver->deleteMultiple($sanitizedIds);
    }

    /**
     * PSR-16 wrapper for "deleteItems"
     * @param array $keys An array of keys that should be removed from the pool.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function deleteMultiple($keys) : bool
    {
        return $this->deleteItems($keys);
    }

    /**
     * Deletes all items in the pool.
     *
     * @return bool True if the pool was successfully cleared. False if there was an error.
     */
    public function clear() : bool
    {
        return $this->_driver->clear();
    }

    /**
     * Garbage collection function (if supported by the driver)
     *
     * @throws Exception
     */
    public function garbageCollect() : void
    {
        if (!$this->_driver instanceof GarbageCollect)
        {
            throw new Exception('This cache driver does not support garbage collection.');
        }

        $this->_driver->garbageCollect();
    }

    /**
     * Replaces troublesome characters with underscores and adds prefix to avoid duplicates
     *
     * @param string $id id of cache to sanitize
     *
     * @return  string
     *
     * @throws Exception
     */
    protected function _sanitizeId(string $id): string
    {
        // configuration for the specific cache group
        try
        {
            $prefix = $this->_config['prefix'] ?? \Modseven\Config::instance()->load('cache.prefix');
        }
        catch (\Modseven\Exception $e)
        {
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }

        // sha1 the id makes sure name is not too long and has not any not allowed characters
        return $prefix . sha1($id);
    }
}
