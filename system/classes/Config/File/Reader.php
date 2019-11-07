<?php
/**
 * File-based configuration reader. Multiple configuration directories can be
 * used by attaching multiple instances of this class to Config.
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\Config\File;

use JsonException;
use Modseven\Core;
use Modseven\Arr;
use Modseven\Profiler;
use Modseven\Exception;

class Reader implements \Modseven\Config\Reader
{
    /**
     * Cached Configurations
     * @var array
     */
    protected static array $_cache;

    /**
     * The directory where config files are located
     * @var string
     */
    protected string $_directory = '';

    /**
     * Creates a new file reader using the given directory as a config source
     *
     * @param string $directory Configuration directory to search
     */
    public function __construct(string $directory = 'config')
    {
        $this->_directory = trim($directory, '/');
    }

    /**
     * Load and merge all of the configuration files in this group.
     *
     * @param string $group Configuration group name
     *
     * @throws Exception
     *
     * @return array
     */
    public function load(string $group): array
    {
        // Check caches and start Profiling
        if (Core::$caching && isset(self::$_cache[$group]))
        {
            // This group has been cached
            return self::$_cache[$group];
        }

        if (Core::$profiling)
        {
            // Start a new benchmark
            $benchmark = Profiler::start('Config', __FUNCTION__);
        }

        // Init
        $config = [];

        // Loop through paths. Notice: array_reverse, so system files get overwritten by app files
        foreach (array_reverse(Core::includePaths()) as $path)
        {
            // Build path
            $file = $path . 'config' . DIRECTORY_SEPARATOR . $group;
            $value = false;

            // Try .php .json and .yaml extensions and parse contents with PHP support
            if (file_exists($path = $file . '.php'))
            {
                $value = Core::load($path);
            }
            elseif (file_exists($path = $file . '.json'))
            {
                try
                {
                    $value = json_decode($this->readFromOb($path), true, 512, JSON_THROW_ON_ERROR);
                }
                catch (JsonException $e)
                {
                    throw new Exception('Error parsing JSON configuration file: :file', [
                        ':file' => $path
                    ], $e->getCode(), $e);
                }
            }
            elseif (file_exists($path = $file . '.yaml'))
            {
                if (!extension_loaded('yaml'))
                {
                    throw new Exception('PECL Yaml Extension is required in order to parse YAML Config');
                }
                $value = yaml_parse($this->readFromOb($path));
            }

            // Merge config
            if ($value !== false)
            {
                $config = Arr::merge($config, $value);
            }
        }

        if (Core::$caching)
        {
            self::$_cache[$group] = $config;
        }

        if (isset($benchmark))
        {
            // Stop the benchmark
            Profiler::stop($benchmark);
        }

        return $config;
    }

    /**
     * Read Contents from file with output buffering.
     * Used to support <?php ?> tags and code inside Configurations
     *
     * @param string $path Path to File
     *
     * @return false|string
     */
    protected function readFromOb(string $path)
    {
        // Start output buffer
        ob_start();

        Core::load($path);

        return ob_get_clean();
    }
}
