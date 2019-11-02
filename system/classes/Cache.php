<?php
/**
 * Modseven Cache provides a common interface to a variety of caching engines. Tags are
 * supported where available natively to the cache system. Modseven Cache supports multiple
 * instances of cache engines through a grouped singleton pattern.
 *
 * ### Supported cache engines
 * *  [APC](https://www.php.net/manual/de/book.apcu.php)
 * *  [Memcached](https://www.php.net/manual/de/book.memcached.php)
 * *  [SQLite](http://www.sqlite.org/)
 * *  [Redis](https://redislabs.com/lp/php-redis/)
 * *  File
 *
 * ### Introduction to caching
 * Caching should be implemented with consideration. Generally, caching the result of resources
 * is faster than reprocessing them. Choosing what, how and when to cache is vital. PHP APC is
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
 * In cases where only one cache group is required, set `Cache::$default` (in your bootstrap,
 * or by extending `Modseven_Cache` class) to the name of the group.
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven;

use Modseven\Cache\Exception;

abstract class Cache
{
    /**
     * Default Expiration Date
     */
    public const DEFAULT_EXPIRE = 3600;

    /**
     * Default driver to use
     *
     * @var string
     */
    public static string $default = 'file';

    /**
     * Cache instances
     *
     * @var array
     */
    public static array $instances = [];

    /**
     * Configuration array
     *
     * @var array
     */
    protected array $_config = [];

    /**
     * Ensures singleton pattern is observed, loads the default expiry
     *
     * @param array $config configuration
     */
    protected function __construct(array $config)
    {
        $this->config($config);
    }

    /**
     * Getter and setter for the configuration. If no argument provided, the
     * current configuration is returned. Otherwise the configuration is set
     * to this class.
     *
     * @param mixed    key to set to array, either array or config path
     * @param mixed    value to associate with key
     *
     * @return  mixed
     */
    public function config($key = null, $value = null)
    {
        if ($key === null)
        {
            return $this->_config;
        }

        if (is_array($key))
        {
            $this->_config = $key;
        }
        else
        {
            if ($value === null)
            {
                return Arr::get($this->_config, $key);
            }

            $this->_config[$key] = $value;
        }

        return $this;
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
    public static function instance(?string $group = null): Cache
    {
        // If there is no group supplied, try to get it from the config
        if ($group === null)
        {
            try
            {
                $group = Core::$config->load('cache.default');
            }
            catch (\Modseven\Exception $e)
            {
                throw new Exception($e->getMessage(), null, $e->getCode(), $e);
            }

            // If there is no group supplied
            if ($group === null)
            {
                // Use the default setting
                $group = static::$default;
            }
        }

        if (isset(static::$instances[$group]))
        {
            // Return the current group if initiated already
            return static::$instances[$group];
        }

        try
        {
            $config = Core::$config->load('cache');
        }
        catch (\Modseven\Exception $e)
        {
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }

        if ( ! $config->offsetExists($group))
        {
            throw new Exception(
                'Failed to load Modseven Cache group: :group',
                [':group' => $group]
            );
        }

        $config = $config->get($group);

        // Create a new cache type instance
        $cache_class = $config['driver'];
        static::$instances[$group] = new $cache_class($config);

        // Return the instance
        return static::$instances[$group];
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
    protected function _sanitize_id(string $id): string
    {
        // configuration for the specific cache group
        try
        {
            $prefix = $this->_config['prefix'] ?? Core::$config->load('cache.prefix');
        }
        catch (\Modseven\Exception $e)
        {
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }

        // sha1 the id makes sure name is not too long and has not any not allowed characters
        return $prefix . sha1($id);
    }

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
     * Retrieve a cached value entry by id.
     *
     * @param string $id      id of cache to entry
     * @param mixed  $default default value to return if cache miss
     *
     * @return  mixed
     *
     * @throws  Exception
     */
    abstract public function get(string $id, $default = null);

    /**
     * Set a value to cache with id and lifetime
     *
     * @param string  $id       id of cache entry
     * @param mixed   $data     data to set to cache
     * @param integer $lifetime lifetime in seconds
     *
     * @return  boolean
     */
    abstract public function set(string $id, $data, int $lifetime = 3600): bool;

    /**
     * Delete a cache entry based on id
     *
     * @param string $id id to remove from cache
     *
     * @return  boolean
     */
    abstract public function delete(string $id): bool;

    /**
     * Delete all cache entries.
     * Beware of using this method when
     * using shared memory cache systems, as it will wipe every
     * entry within the system for all clients.
     *
     * @return  boolean
     */
    abstract public function delete_all(): bool;
}
