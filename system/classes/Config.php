<?php
/**
 * Wrapper for configuration arrays. Multiple configuration readers can be
 * attached to allow loading configuration from files, database, etc.
 *
 * Configuration directives cascade across config sources in the same way that
 * files cascade across the filesystem.
 *
 * Directives from sources high in the sources list will override ones from those
 * below them.
 *
 * @package    Modseven
 * @category   Configuration
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

use Modseven\Config\Source;
use Modseven\Config\Reader;
use Modseven\Config\Writer;
use Modseven\Config\Group;

class Config
{
    /**
     * Configuration readers
     * @var array
     */
    protected array $_sources = [];

    /**
     * Array of config groups
     * @var array
     */
    protected array $_groups = [];

    /**
     * Attach a configuration reader. By default, the reader will be added as
     * the first used reader. However, if the reader should be used only when
     * all other readers fail, use `FALSE` for the second parameter.
     *
     *     $config->attach($reader);        // Try first
     *     $config->attach($reader, FALSE); // Try last
     *
     * @param Source $source instance
     * @param boolean $first add the reader as the first used object
     * @return  self
     */
    public function attach(Source $source, bool $first = TRUE): self
    {
        if ($first === TRUE) {
            // Place the log reader at the top of the stack
            array_unshift($this->_sources, $source);
        } else {
            // Place the reader at the bottom of the stack
            $this->_sources[] = $source;
        }

        // Clear any cached _groups
        $this->_groups = [];

        return $this;
    }

    /**
     * Detach a configuration reader.
     *
     *     $config->detach($reader);
     *
     * @param Source $source instance
     * @return  self
     */
    public function detach(Source $source): self
    {
        if (($key = array_search($source, $this->_sources, true)) !== FALSE) {
            // Remove the writer
            unset($this->_sources[$key]);
        }

        return $this;
    }

    /**
     * Copy one configuration group to all of the other writers.
     *
     * @param string $group configuration group name
     *
     * @return  self
     *
     * @throws Exception
     */
    public function copy(string $group): self
    {
        // Load the configuration group
        foreach ($this->load($group)->as_array() as $key => $value) {
            $this->_write_config($group, $key, $value);
        }

        return $this;
    }

    /**
     * Load a configuration group. Searches all the config sources, merging all the
     * directives found into a single config group.  Any changes made to the config
     * in this group will be mirrored across all writable sources.
     *
     *     $array = $config->load($name);
     *
     * See [Modseven_Config_Group] for more info
     *
     * @param   string $group configuration group name
     * @return  mixed
     * @throws  Exception
     */
    public function load(string $group)
    {
        if (!count($this->_sources)) {
            throw new Exception('No configuration sources attached');
        }

        if (empty($group)) {
            throw new Exception('Need to specify a config group');
        }

        if (strpos($group, '.') !== FALSE) {
            // Split the config group and path
            [$group, $path] = explode('.', $group, 2);
        }

        if (isset($this->_groups[$group])) {
            if (isset($path)) {
                return Arr::path((array)$this->_groups[$group], $path, NULL, '.');
            }
            return $this->_groups[$group];
        }

        $config = [];

        // We search from the "lowest" source and work our way up
        foreach (array_reverse($this->_sources) as $source) {
            if (($source instanceof Reader) && $source_config = $source->load($group)) {
                $config = Arr::merge($config, $source_config);
            }
        }

        $this->_groups[$group] = new Group($this, $group, $config);

        if (isset($path)) {
            return Arr::path($config, $path, NULL, '.');
        }

        return $this->_groups[$group];
    }

    /**
     * Callback used by the config group to store changes made to configuration
     *
     * @param string $group Group name
     * @param string $key Variable name
     * @param mixed $value The new value
     * @return self
     */
    public function _write_config(string $group, string $key, $value): self
    {
        foreach ($this->_sources as $source) {
            if (!($source instanceof Writer)) {
                continue;
            }

            // Copy each value in the config
            $source->write($group, $key, $value);
        }

        return $this;
    }

}
