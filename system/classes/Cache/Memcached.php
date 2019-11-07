<?php
/**
 * Modseven_Cache_Memcached class
 * LICENSE: THE WORK (AS DEFINED BELOW) IS PROVIDED UNDER THE TERMS OF THIS
 * CREATIVE COMMONS PUBLIC LICENSE ("CCPL" OR "LICENSE"). THE WORK IS PROTECTED
 * BY COPYRIGHT AND/OR OTHER APPLICABLE LAW. ANY USE OF THE WORK OTHER THAN AS
 * AUTHORIZED UNDER THIS LICENSE OR COPYRIGHT LAW IS PROHIBITED.
 * BY EXERCISING ANY RIGHTS TO THE WORK PROVIDED HERE, YOU ACCEPT AND AGREE TO
 * BE BOUND BY THE TERMS OF THIS LICENSE. TO THE EXTENT THIS LICENSE MAY BE
 * CONSIDERED TO BE A CONTRACT, THE LICENSOR GRANTS YOU THE RIGHTS CONTAINED HERE
 * IN CONSIDERATION OF YOUR ACCEPTANCE OF SUCH TERMS AND CONDITIONS.
 *
 * @author         gimpe <gimpehub@intljaywalkers.com>
 * @copyright      2011 International Jaywalkers
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 *
 * @license        http://creativecommons.org/licenses/by/3.0/ CC BY 3.0
 * @link           http://github.com/gimpe/modseven-memcached
 */

namespace Modseven\Cache;

use Modseven\Arr;
use Modseven\Cache;

class Memcached extends Cache
{
    /**
     * Holds the cache instance
     * @var \Memcached
     */
    protected \Memcached $memcached_instance;

    /**
     * Memcached constructor.
     *
     * @param array $config
     *
     * @throws Exception
     */
    protected function __construct(array $config)
    {
        // Check if memcached is present
        if ( ! extension_loaded('memcached'))
        {
            throw new Exception('Memcached extension is not loaded.');
        }

        // Call parent constructor
        parent::__construct($config);

        // Initialize Memcache
        $this->memcached_instance = new \Memcached;

        // load servers from configuration
        $servers = Arr::get($this->_config, 'servers', []);

        // Check if there are configured server
        if (empty($servers))
        {
            throw new Exception('No Memcached servers in config/cache.php');
        }

        // Load and set options
        foreach (Arr::get($this->_config, 'options', []) as $option => $value)
        {
            // Check if exception serializer Igbinary is supported
            if ($option === \Memcached::OPT_SERIALIZER && $value === \Memcached::SERIALIZER_IGBINARY && ! \Memcached::HAVE_IGBINARY)
            {
                throw new Exception('Serializer Igbinary not supported, please fix config/cache.php');
            }

            // Check if exception serializer JSON is supported
            if ($option === \Memcached::OPT_SERIALIZER && $value === \Memcached::SERIALIZER_JSON && ! \Memcached::HAVE_JSON)
            {
                throw new Exception('serializer JSON not supported, please fix config/cache.php');
            }

            $this->memcached_instance->setOption($option, $value);
        }

        // add servers
        foreach ($servers as $pos => $server)
        {
            $host = Arr::get($server, 'host');
            $port = Arr::get($server, 'port', null);
            $weight = Arr::get($server, 'weight', null);
            $status = Arr::get($server, 'status', true);

            if ( ! empty($host))
            {
                // status can be used by an external healthcheck to mark the memcached instance offline
                if ($status === true)
                {
                    $this->memcached_instance->addServer($host, $port, $weight);

                    // Check if the connection succeeded throw exception otherwise
                    $grp = $host . ':' . $port;
                    if (!isset($this->memcached_instance->getStats()[$grp]))
                    {
                        throw new Exception('Could not connect to Memcached Server (Host: "' . $host . '" Port: "' . $port . '") ERROR: "' . $this->memcached_instance->getResultMessage() . '"');
                    }
                }
            }
            else
            {
                // exception no server host
                throw new Exception('no host defined for server[' . $pos . '] in config/cache.php');
            }
        }
    }

    /**
     * Retrieve a cached value entry by Id.
     *
     * @param string  $id       Id of cache to entry
     * @param mixed   $default  Default value to return if cache miss
     *
     * @return  mixed
     *
     * @throws  Exception
     */
    public function get(string $id, $default = null)
    {
        $result = $this->memcached_instance->get($this->_sanitizeId($id));

        if ($this->memcached_instance->getResultCode() !== \Memcached::RES_SUCCESS)
        {
            $result = $default;
        }

        return $result;
    }

    /**
     * Set a value to cache with id and lifetime
     *
     * @param string  $id       Id of cache entry
     * @param string  $data     Data to set to cache
     * @param integer $lifetime Lifetime in seconds
     *
     * @return  bool
     *
     * @throws Exception
     */
    public function set(string $id, $data, int $lifetime = 3600) : bool
    {
        return $this->memcached_instance->set($this->_sanitizeId($id), $data, $lifetime);
    }

    /**
     * Delete a cache entry based on id
     *
     * @param string  $id Id to remove from cache
     *
     * @return bool
     *
     * @throws Exception
     */
    public function delete(string $id) : bool
    {
        return $this->memcached_instance->delete($this->_sanitizeId($id));
    }

    /**
     * Delete all cache entries.
     * Beware of using this method when using shared memory cache systems, as it will wipe every
     * entry within the system for all clients.
     *
     * @return bool
     */
    public function deleteAll() : bool
    {
        return $this->memcached_instance->flush();
    }
}
