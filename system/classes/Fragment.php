<?php
/**
 * View fragment caching. This is primarily used to cache small parts of a view
 * that rarely change. For instance, you may want to cache the footer of your
 * template because it has very little dynamic content. Or you could cache a
 * user profile page and delete the fragment when the user updates.
 *
 * For obvious reasons, fragment caching should not be applied to any
 * content that contains forms.
 *
 * [!!] Multiple language (I18n) support was added in v3.0.4.
 *
 * @package    Modseven
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 * @uses       Core::cache
 */

namespace Modseven;

class Fragment
{
    /**
     * default number of seconds to cache for
     * @var integer
     */
    public static int $lifetime = 30;

    /**
     * use multilingual fragment support?
     * @var boolean
     */
    public static bool $i18n = false;

    /**
     * list of buffer => cache key
     * @var array
     */
    protected static array $_caches = [];

    /**
     * Load a fragment from cache and display it. Multiple fragments can
     * be nested with different life times.
     *
     * @param string $name fragment name
     * @param integer $lifetime fragment cache lifetime
     * @param boolean $i18n multilingual fragment support
     *
     * @return  boolean
     *
     * @throws Exception
     */
    public static function load(string $name, ?int $lifetime = NULL, ?bool $i18n = NULL): bool
    {
        // Set the cache lifetime
        $lifetime = ($lifetime === NULL) ? static::$lifetime : (int)$lifetime;

        // Get the cache key name
        $cache_key = self::_cache_key($name, $i18n);

        if ($fragment = Core::cache($cache_key, NULL, $lifetime)) {
            // Display the cached fragment now
            echo $fragment;

            return TRUE;
        }

        // Start the output buffer
        ob_start();

        // Store the cache key by the buffer level
        static::$_caches[ob_get_level()] = $cache_key;

        return FALSE;
    }

    /**
     * Generate the cache key name for a fragment.
     *
     * @param string $name fragment name
     * @param boolean $i18n multilingual fragment support
     * @return  string
     */
    protected static function _cache_key(string $name, bool $i18n = NULL): string
    {
        if ($i18n === NULL) {
            // Use the default setting
            $i18n = static::$i18n;
        }

        // Language prefix for cache key
        $i18n = ($i18n === TRUE) ? I18n::lang() : '';

        // Note: $i18n and $name need to be delimited to prevent naming collisions
        return '\Modseven\Fragment::cache(' . $i18n . '+' . $name . ')';
    }

    /**
     * Saves the currently open fragment in the cache.
     *
     * @return  void
     *
     * @throws Exception
     */
    public static function save(): void
    {
        // Get the buffer level
        $level = ob_get_level();

        if (isset(static::$_caches[$level])) {
            // Get the cache key based on the level
            $cache_key = static::$_caches[$level];

            // Delete the cache key, we don't need it anymore
            unset(static::$_caches[$level]);

            // Get the output buffer and display it at the same time
            $fragment = ob_get_flush();

            // Cache the fragment
            Core::cache($cache_key, $fragment);
        }
    }

    /**
     * Delete a cached fragment.
     *
     * @param string $name fragment name
     * @param boolean $i18n multilingual fragment support
     *
     * @return  void
     *
     * @throws Exception
     */
    public static function delete(string $name, ?bool $i18n = NULL): void
    {
        // Invalid the cache
        Core::cache(self::_cache_key($name, $i18n), NULL, -3600);
    }

}
