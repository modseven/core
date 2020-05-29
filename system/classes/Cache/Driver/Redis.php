<?php
/**
 * Cache Class for Redis
 *
 * - requires php "redis" extension
 *
 * @package    KO7\Cache
 * @category   Driver
 *
 * @copyright  (c) Yoshiharu Shibata <shibata@zoga.me> and Chris Go <chris@velocimedia.com>
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\Cache\Driver;

use Modseven\Arr;
use Modseven\Cache\Driver;
use Modseven\Cache\Exception;

class Redis extends Driver implements Tagging
{
    /**
     * Redis instance
     * @var \Redis
     */
    protected \Redis $_redis;

    /**
     * Prefix for tag
     * @var string
     */
    protected string $_tag_prefix = '_tag';

    /**
     * Ensures singleton pattern is observed, loads the default expiry
     *
     * @param  array $config            Configuration
     *
     * @throws Exception
     */
    public function __construct(array $config)
    {
        // We need the redis extension in order to work further so we check this first
        if (!extension_loaded('redis'))
        {
            throw new Exception('Redis PHP extension not loaded but required to use Redis cache class!');
        }

        parent::__construct($config);

        // Get Configured Servers
        $servers = Arr::get($this->_config, 'servers', NULL);
        if (empty($servers))
        {
            throw new Exception('No Redis servers defined in configuration. Please define at least one.');
        }

        // Now instance the redis server and configure it
        $this->_redis = new \Redis();

        // Global cache prefix so the keys in redis is organized
        $cache_prefix = Arr::get($this->_config, 'cache_prefix', NULL);
        $this->_tag_prefix = Arr::get($this->_config, 'tag_prefix', $this->_tag_prefix). ':';


        foreach($servers as $server)
        {
            // Determine Connection method and connect
            $method = Arr::get($server, 'persistent', FALSE) ? 'pconnect': 'connect';
            $this->_redis->{$method}($server['host'], $server['port'], 1);

            // See if there is a password
            $password = Arr::get($server, 'password', NULL);
            if (!empty($password))
            {
                $this->_redis->auth($password);
            }

            // Prefix a name space
            $prefix = Arr::get($server, 'prefix', NULL);
            if (!empty($prefix))
            {
                if (!empty($cache_prefix))
                {
                    $prefix .= ':'.$cache_prefix;
                }

                $prefix .= ':';
                $this->_redis->setOption(\Redis::OPT_PREFIX, $prefix);
            }
        }

        // Tell redis to serialize using php serializer
        $this->_redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);

    }

    /**
     * @inheritDoc
     */
    public function has(string $key) : bool
    {
        return $this->_redis->exists($key);
    }

    /**
     * @inheritDoc
     */
    public function get(string $key)
    {
        return $this->_redis->get($key);
    }

    /**
     * @inheritDoc
     */
    public function getMultiple(array $keys) : array
    {
        return array_combine($keys, $this->_redis->mget($keys));
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $data, $lifetime = 3600) : bool
    {
        // Fit try to set the element
        if (!$this->_redis->set($key, $data))
        {
            return false;
        }

        // If successful let's set the ttl now
        $this->_redis->expire($key, $lifetime);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple(array $items) : bool
    {
        $normalized = [];
        foreach ($items as $key => $item) {
            $normalized[$key] = $item['value'];
        }

        // Use redis mset to set all at once
        $success = $this->_redis->mset($normalized);

        if (!$success)
        {
            return false;
        }

        // Now we need to loop again to set the lifetime of those values
        foreach ($items as $key => $item) {
            $this->_redis->expire($key, $item['lifetime']);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $id) : bool
    {
        return $this->_redis->del($id) >= 1;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple(array $keys) : bool
    {
        return $this->_redis->del(...$keys) === count($keys);
    }

    /**
     * @inheritDoc
     */
    public function clear() : bool
    {
        return $this->_redis->flushDB();
    }

    /**
     * @inheritDoc
     */
    public function setWithTags(string $id, $data, array $tags, ?int $lifetime = 3600) : bool
    {
        $result = $this->set($id, $data, $lifetime);

        if ($result)
        {
            foreach ($tags as $tag)
            {
                $this->_redis->lPush($this->_tag_prefix.$tag, $id);
            }
        }

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function deleteTag(string $tag) : bool
    {
        if ($this->_redis->exists($this->_tag_prefix.$tag))
        {
            // Delete the items
            $keys = $this->_redis->lRange($this->_tag_prefix.$tag, 0, -1);
            $this->deleteMultiple($keys);

            // Then delete the tag itself
            $this->_redis->del($this->_tag_prefix.$tag);

            return true;
        }

        return false;
    }

    /**
     * @inheritDoc
     */
    public function getWithTag(string $tag) : array
    {
        if ($this->_redis->exists($this->_tag_prefix.$tag))
        {
            $keys = $this->_redis->lRange($this->_tag_prefix.$tag, 0, -1);

            $rows = [];
            foreach ($keys as $key)
            {
                $rows[$key] = $this->get($key);
            }

            return $rows;
        }

        return [];
    }
}