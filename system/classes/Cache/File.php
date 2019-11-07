<?php
/**
 * File Cache driver. Provides a file based driver for the Modseven Cache library.
 * This is one of the slowest caching methods.
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\Cache;

use SplFileInfo;
use ErrorException;
use DirectoryIterator;
use UnexpectedValueException;

use Modseven\Arr;
use Modseven\Cache;

class File extends Cache implements GarbageCollect
{
    /**
     * The caching directory
     * @var null|SplFileInfo
     */
    protected ?SplFileInfo $_cache_dir = null;

    /**
     * Does the cache directory exist and is writeable?
     * @var boolean
     */
    protected bool $_cache_dir_usable = false;

    /**
     * Creates a hashed filename based on the string. This is used
     * to create shorter unique IDs for each cache filename.
     *
     * @param string $string string to hash into filename
     *
     * @return string
     */
    protected static function filename(string $string) : string
    {
        return sha1($string) . '.cache';
    }

    /**
     * Check that the cache directory exists and writeable. Attempts to create
     * it if not exists.
     *
     * @throws  Exception
     */
    protected function _checkCacheDir()
    {
        $directory = Arr::get($this->_config, 'cache_dir', APPPATH . 'cache');

        try
        {
            $this->_cache_dir = new SplFileInfo($directory);
        }
        catch (UnexpectedValueException $e)
        {
            $this->_cache_dir = $this->_makeDirectory($directory, 0777, true);
        }

        // If the defined directory is a file, get outta here
        if ($this->_cache_dir->isFile() || ! $this->_cache_dir->isReadable() || ! $this->_cache_dir->isWritable())
        {
            throw new Exception('Unable to use cache directory. Make sure it exists and that you have correct read/write permissions : :resource', [
                ':resource' => $this->_cache_dir->getRealPath()
            ]);
        }

        $this->_cache_dir_usable = true;
    }

    /**
     * Retrieve a cached value entry by id.
     *
     * @param string $id      id of cache to entry
     * @param mixed  $default default value to return if cache miss
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function get(string $id, $default = null)
    {
        if ( ! $this->_cache_dir_usable)
        {
            $this->_checkCacheDir();
        }

        $filename = self::filename($this->_sanitizeId($id));
        $directory = $this->_resolveDirectory($filename);

        // Wrap operations in try/catch to handle notices
        try
        {
            // Open file
            $file = new SplFileInfo($directory . $filename);

            // If file does not exist
            if ( ! $file->isFile())
            {
                // Return default value
                return $default;
            }

            // Test the expiry
            if ($this->_isExpired($file))
            {
                // Delete the file
                $this->_deleteFile($file, false, true);
                return $default;
            }

            // open the file to read data
            $data = $file->openFile();

            // Run first fgets(). Cache data starts from the second line
            // as the first contains the lifetime timestamp
            $data->fgets();

            $cache = '';

            while ($data->eof() === false) {
                $cache .= $data->fgets();
            }

            return unserialize($cache);
        }
        catch (ErrorException $e)
        {
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }
    }

    /**
     * Set a value to cache with id and lifetime
     *
     * @param string  $id       id of cache entry
     * @param mixed   $data     data to set to cache
     * @param int     $lifetime lifetime in seconds
     *
     * @return  bool
     *
     * @throws Exception
     */
    public function set(string $id, $data, ?int $lifetime = null) : bool
    {
        if ( ! $this->_cache_dir_usable)
        {
            $this->_checkCacheDir();
        }

        $filename = self::filename($this->_sanitizeId($id));
        $directory = $this->_resolveDirectory($filename);

        // If lifetime is NULL
        if ($lifetime === null)
        {
            // Set to the default expiry
            $lifetime = Arr::get($this->_config, 'default_expire', Cache::DEFAULT_EXPIRE);
        }

        // Open directory
        $dir = new SplFileInfo($directory);

        // If the directory path is not a directory
        if ( ! $dir->isDir())
        {
            $this->_makeDirectory($directory, 0777, true);
        }

        // Open file to inspect
        $resource = new SplFileInfo($directory . $filename);
        $file = $resource->openFile('w');

        try
        {
            $data = $lifetime . "\n" . serialize($data);
            $file->fwrite($data, strlen($data));
            return (bool)$file->fflush();
        }
        catch (ErrorException $e)
        {
            // Throw a caching error
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }
    }

    /**
     * Delete a cache entry based on id
     *
     * @param string $id id to remove from cache
     *
     * @return bool
     *
     * @throws Exception
     */
    public function delete(string $id) : bool
    {
        if ( ! $this->_cache_dir_usable)
        {
            $this->_checkCacheDir();
        }

        $filename = self::filename($this->_sanitizeId($id));
        $directory = $this->_resolveDirectory($filename);

        return $this->_deleteFile(new SplFileInfo($directory . $filename), false, true);
    }

    /**
     * Delete all cache entries.
     * Beware of using this method when
     * using shared memory cache systems, as it will wipe every
     * entry within the system for all clients.
     *
     * @return  bool
     *
     * @throws Exception
     */
    public function deleteAll() : bool
    {
        $this->_cache_dir_usable or $this->_checkCacheDir();

        return $this->_deleteFile($this->_cache_dir, true);
    }

    /**
     * Garbage collection method that cleans any expired
     * cache entries from the cache.
     *
     * @throws Exception
     */
    public function garbageCollect() : void
    {
        $this->_cache_dir_usable or $this->_checkCacheDir();
        $this->_deleteFile($this->_cache_dir, true, false, true);
    }

    /**
     * Deletes files recursively and returns FALSE on any errors
     *
     * @param SplFileInfo $file                    file
     * @param boolean     $retain_parent_directory retain the parent directory
     * @param boolean     $ignore_errors           ignore_errors to prevent all exceptions interrupting exec
     * @param boolean     $only_expired            only expired files
     *
     * @return bool
     *
     * @throws Exception
     */
    protected function _deleteFile(SplFileInfo $file, bool $retain_parent_directory = false, bool $ignore_errors = false, bool $only_expired = false) : bool
    {
        // Allow graceful error handling
        try
        {
            // If is file
            if ($file->isFile())
            {
                try
                {
                    // Handle ignore files
                    if (in_array($file->getFilename(), $this->config('ignore_on_delete'), true))
                    {
                        $delete = false;
                    }
                    // If only expired is not set
                    elseif ($only_expired === false)
                    {
                        // We want to delete the file
                        $delete = true;
                    }
                    // Otherwise...
                    else
                        {
                        // Assess the file expiry to flag it for deletion
                        $delete = $this->_isExpired($file);
                    }

                    // If the delete flag is set delete file
                    if ($delete === true)
                    {
                        return unlink($file->getRealPath());
                    }

                    return false;
                }
                catch (ErrorException $e)
                {
                    throw new Exception($e->getMessage(), null, $e->getCode(), $e);
                }
            }
            // Else, is directory
            elseif ($file->isDir())
            {
                // Create new DirectoryIterator
                $files = new DirectoryIterator($file->getPathname());

                // Iterate over each entry
                while ($files->valid())
                {
                    // Extract the entry name
                    $name = $files->getFilename();

                    // If the name is not a dot
                    if ($name !== '.' && $name !== '..')
                    {
                        // Create new file resource
                        $fp = new SplFileInfo($files->getRealPath());
                        // Delete the file
                        $this->_deleteFile($fp, $retain_parent_directory, $ignore_errors, $only_expired);
                    }

                    // Move the file pointer on
                    $files->next();
                }

                // If set to retain parent directory, return now
                if ($retain_parent_directory)
                {
                    return true;
                }

                try
                {
                    // Remove the files iterator
                    // (fixes Windows PHP which has permission issues with open iterators)
                    unset($files);

                    // Try to remove the parent directory
                    return rmdir($file->getRealPath());
                }
                catch (ErrorException $e)
                {
                    throw new Exception($e->getMessage(), null, $e->getCode(), $e);
                }
            }
            else
            {
                // We get here if a file has already been deleted
                return false;
            }
        }
        // Catch all exceptions
        catch (Exception $e)
        {
            // If ignore_errors is on
            if ($ignore_errors === true)
            {
                // Return
                return false;
            }

            // Throw exception
            throw $e;
        }
    }

    /**
     * Resolves the cache directory real path from the filename
     *
     * @param string $filename filename to resolve
     *
     * @return string
     */
    protected function _resolveDirectory(string $filename) : string
    {
        return $this->_cache_dir->getRealPath() . DIRECTORY_SEPARATOR . $filename[0] . $filename[1] . DIRECTORY_SEPARATOR;
    }

    /**
     * Makes the cache directory if it doesn't exist. Simply a wrapper for
     * `mkdir` to ensure DRY principles
     *
     * @param string   $directory directory path
     * @param integer  $mode      chmod mode
     * @param boolean  $recursive allows nested directories creation
     * @param resource $context   a stream context
     *
     * @return SplFileInfo
     *
     * @throws Exception
     */
    protected function _makeDirectory(string $directory, int $mode = 0777, bool $recursive = false, $context = null) : SplFileInfo
    {
        // call mkdir according to the availability of a passed $context param
        $mkdir_result = $context ?
            mkdir($directory, $mode, $recursive, $context) :
            mkdir($directory, $mode, $recursive);

        // throw an exception if unsuccessful
        if ( ! $mkdir_result) {
            throw new Exception('Failed to create the defined cache directory : :directory', [
                ':directory' => $directory
            ]);
        }

        // chmod to solve potential umask issues
        chmod($directory, $mode);

        return new SplFileInfo($directory);
    }

    /**
     * Test if cache file is expired
     *
     * @param SplFileInfo $file the cache file
     *
     * @return bool TRUE if expired false otherwise
     *
     * @throws Exception
     */
    protected function _isExpired(SplFileInfo $file) : bool
    {
        // Open the file and parse data
        $created = $file->getMTime();
        $data = $file->openFile('r');
        $lifetime = (int)$data->fgets();

        // If we're at the EOF at this point, corrupted!
        if ($data->eof()) {
            throw new Exception(__METHOD__ . ' corrupted cache file!');
        }

        // close file
        $data = null;

        // test for expiry and return
        return (($lifetime !== 0) && (($created + $lifetime) < time()));
    }
}