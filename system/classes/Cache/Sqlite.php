<?php
/**
 * Modseven Cache Sqlite Driver
 * Requires SQLite3 and PDO
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\Cache;

use PDO;
use PDOException;
use Modseven\Arr;
use Modseven\Cache;

class Sqlite extends Cache implements Tagging, GarbageCollect
{
    /**
     * Database resource
     * @var PDO
     */
    protected PDO $_db;

    /**
     * Sets up the PDO SQLite table and
     * initialises the PDO connection
     *
     * @param array $config configuration
     *
     * @throws  Exception
     */
    protected function __construct(array $config)
    {
        parent::__construct($config);

        $database = Arr::get($this->_config, 'database', null);

        if ($database === null)
        {
            throw new Exception('Database path not available in Cache configuration');
        }

        // Load new Sqlite DB
        $this->_db = new PDO('sqlite:' . $database);

        // Test for existing DB
        $result = $this->_db->query("SELECT * FROM sqlite_master WHERE name = 'caches' AND type = 'table'")->fetchAll();

        // If there is no table, create a new one
        if (count($result) === 0)
        {
            $database_schema = Arr::get($this->_config, 'schema', null);

            if ($database_schema === null)
            {
                throw new Exception('Database schema not found in Cache configuration');
            }

            try
            {
                // Create the caches table
                $this->_db->exec(Arr::get($this->_config, 'schema', null));
            }
            catch (PDOException $e)
            {
                throw new Exception('Failed to create new SQLite caches table with the following error : :error', [
                    ':error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Retrieve a value based on an id
     *
     * @param string $id      id
     * @param mixed  $default default [Optional] Default value to return if id not found
     *
     * @return  mixed
     *
     * @throws  Exception
     */
    public function get(string $id, $default = null)
    {
        // Prepare statement
        $statement = $this->_db->prepare('SELECT id, expiration, cache FROM caches WHERE id = :id LIMIT 0, 1');

        // Try and load the cache based on id
        try
        {
            $statement->execute([':id' => $this->_sanitize_id($id)]);
        }
        catch (PDOException $e)
        {
            throw new Exception('There was a problem querying the local SQLite3 cache. :error', [
                ':error' => $e->getMessage()
            ]);
        }

        if ( ! $result = $statement->fetch(PDO::FETCH_OBJ))
        {
            return $default;
        }

        // If the cache has expired
        if ($result->expiration !== 0 && $result->expiration <= time())
        {
            // Delete it and return default value
            $this->delete($id);
            return $default;
        }

        // Disable notices for unserializing
        $ER = error_reporting(~E_NOTICE);

        // Return the valid cache data
        $data = unserialize($result->cache);

        // Turn notices back on
        error_reporting($ER);

        // Return the resulting data
        return $data;
    }

    /**
     * Set a value based on an id. Optionally add tags.
     *
     * @param string  $id       id
     * @param mixed   $data     data
     * @param integer $lifetime lifetime [Optional]
     *
     * @return  bool
     *
     * @throws Exception
     */
    public function set(string $id, $data, ?int $lifetime = null) : bool
    {
        return $this->set_with_tags($id, $data, $lifetime);
    }

    /**
     * Delete a cache entry based on id
     *
     * @param string $id id
     *
     * @return bool
     *
     * @throws Exception
     */
    public function delete(string $id) : bool
    {
        // Prepare statement
        $statement = $this->_db->prepare('DELETE FROM caches WHERE id = :id');

        // Remove the entry
        try
        {
            $statement->execute([':id' => $this->_sanitize_id($id)]);
        }
        catch (PDOException $e)
        {
            throw new Exception('There was a problem querying the local SQLite3 cache. :error', [
                ':error' => $e->getMessage()
            ]);
        }

        return (bool)$statement->rowCount();
    }

    /**
     * Delete all cache entries
     *
     * @return  boolean
     *
     * @throws Exception
     */
    public function delete_all() : bool
    {
        // Prepare statement
        $statement = $this->_db->prepare('DELETE FROM caches');

        // Remove the entry
        try
        {
            $statement->execute();
        }
        catch (PDOException $e)
        {
            throw new Exception('There was a problem querying the local SQLite3 cache. :error', [
                ':error' => $e->getMessage()
            ]);
        }

        return (bool)$statement->rowCount();
    }

    /**
     * Set a value based on an id. Optionally add tags.
     *
     * @param string  $id       id
     * @param mixed   $data     data
     * @param integer $lifetime lifetime [Optional]
     * @param array   $tags     tags [Optional]
     *
     * @return bool
     *
     * @throws Exception
     */
    public function set_with_tags(string $id, $data, ?int $lifetime = null, ?array $tags = null) : bool
    {
        // Serialize the data
        $data = serialize($data);

        // Normalise tags
        $tags = (null === $tags) ? null : ('<' . implode('>,<', $tags) . '>');

        // Setup lifetime
        if ($lifetime === null)
        {
            $lifetime = (0 === Arr::get($this->_config, 'default_expire', null)) ? 0 :
                (Arr::get($this->_config, 'default_expire', Cache::DEFAULT_EXPIRE) + time());
        }
        else
        {
            $lifetime = (0 === $lifetime) ? 0 : ($lifetime + time());
        }

        // Prepare statement
        $statement = $this->exists($id)
            ?
            $this->_db->prepare('UPDATE caches SET expiration = :expiration, cache = :cache, tags = :tags WHERE id = :id')
            :
            $this->_db->prepare('INSERT INTO caches (id, cache, expiration, tags) VALUES (:id, :cache, :expiration, :tags)');

        // Try to insert
        try
        {
            $statement->execute([
                ':id' => $this->_sanitize_id($id), ':cache' => $data, ':expiration' => $lifetime, ':tags' => $tags
            ]);
        }
        catch (PDOException $e)
        {
            throw new Exception('There was a problem querying the local SQLite3 cache. :error', [
                ':error' => $e->getMessage()
            ]);
        }

        return (bool)$statement->rowCount();
    }

    /**
     * Delete cache entries based on a tag
     *
     * @param string $tag tag
     *
     * @return bool
     *
     * @throws Exception
     */
    public function delete_tag(string $tag) : bool
    {
        // Prepare the statement
        $statement = $this->_db->prepare('DELETE FROM caches WHERE tags LIKE :tag');

        // Try to delete
        try
        {
            $statement->execute([':tag' => "%<{$tag}>%"]);
        }
        catch (PDOException $e)
        {
            throw new Exception('There was a problem querying the local SQLite3 cache. :error', [
                ':error' => $e->getMessage()
            ]);
        }

        return (bool)$statement->rowCount();
    }

    /**
     * Find cache entries based on a tag
     *
     * @param string $tag tag
     *
     * @return array
     *
     * @throws Exception
     */
    public function find(string $tag) : array
    {
        // Prepare the statement
        $statement = $this->_db->prepare('SELECT id, cache FROM caches WHERE tags LIKE :tag');

        // Try to find
        try
        {
            if ( ! $statement->execute([':tag' => "%<{$tag}>%"]))
            {
                return [];
            }
        }
        catch (PDOException $e)
        {
            throw new Exception('There was a problem querying the local SQLite3 cache. :error', [
                ':error' => $e->getMessage()
            ]);
        }

        $result = [];

        while ($row = $statement->fetchObject())
        {
            // Disable notices for unserializing
            $ER = error_reporting(~E_NOTICE);

            $result[$row->id] = unserialize($row->cache);

            // Turn notices back on
            error_reporting($ER);
        }

        return $result;
    }

    /**
     * Garbage collection method that cleans any expired
     * cache entries from the cache.
     *
     * @throws Exception
     */
    public function garbage_collect() : void
    {
        // Create the sequel statement
        $statement = $this->_db->prepare('DELETE FROM caches WHERE expiration < :expiration');

        try
        {
            $statement->execute([':expiration' => time()]);
        }
        catch (PDOException $e)
        {
            throw new Exception('There was a problem querying the local SQLite3 cache. :error', [
                ':error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Tests whether an id exists or not
     *
     * @param string $id id
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function exists(string $id) : bool
    {
        $statement = $this->_db->prepare('SELECT id FROM caches WHERE id = :id');
        try
        {
            $statement->execute([':id' => $this->_sanitize_id($id)]);
        }
        catch (PDOExeption $e)
        {
            throw new Exception('There was a problem querying the local SQLite3 cache. :error', [
                ':error' => $e->getMessage()
            ]);
        }

        return (bool)$statement->fetchAll();
    }
}