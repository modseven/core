<?php
/**
 * Inflector helper class. Inflection is changing the form of a word based on
 * the context it is used in. For example, changing a word into a plural form.
 *
 * [!!] Inflection is only tested with English, and is will not work with other languages.
 *
 * @package    Modseven
 * @category   Helpers
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

class Inflector
{
    /**
     * cached inflections
     * @var array
     */
    protected static array $cache = [];

    /**
     * uncountable words
     * @var array|null
     */
    protected static ?array $uncountable = null;

    /**
     * irregular words
     * @var array
     */
    protected static array $irregular;

    /**
     * Makes a plural word singular.
     *
     * You can also provide the count to make inflection more intelligent.
     * In this case, it will only return the singular value if the count is
     * greater than one and not zero.
     *
     * [!!] Special inflections are defined in `config/inflector.php`.
     *
     * @param string $str word to make singular
     * @param int|float $count count of thing
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function singular(string $str, $count = NULL): string
    {
        // $count should always be a float
        $count = ($count === NULL) ? 1.0 : (float)$count;

        // Do nothing when $count is not 1
        if ($count !== 1) {
            return $str;
        }

        // Remove garbage
        $str = strtolower(trim($str));

        // Cache key name
        $key = 'singular_' . $str . $count;

        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }

        if (self::uncountable($str)) {
            return static::$cache[$key] = $str;
        }

        if (empty(static::$irregular)) {
            // Cache irregular words
            static::$irregular = \Modseven\Config::instance()->load('inflector')->irregular;
        }

        if ($irregular = array_search($str, static::$irregular, true)) {
            $str = $irregular;
        } elseif (preg_match('/us$/', $str)) {
            // http://en.wikipedia.org/wiki/Plural_form_of_words_ending_in_-us
            // Already singular, do nothing
        } elseif (preg_match('/[sxz]es$/', $str) || preg_match('/[^aeioudgkprt]hes$/', $str)) {
            // Remove "es"
            $str = substr($str, 0, -2);
        } elseif (preg_match('/[^aeiou]ies$/', $str)) {
            // Replace "ies" with "y"
            $str = substr($str, 0, -3) . 'y';
        } elseif (substr($str, -1) === 's' && substr($str, -2) !== 'ss') {
            // Remove singular "s"
            $str = substr($str, 0, -1);
        }

        return static::$cache[$key] = $str;
    }

    /**
     * Checks if a word is defined as uncountable. An uncountable word has a
     * single form. For instance, one "fish" and many "fish", not "fishes".
     *
     * If you find a word is being pluralized improperly, it has probably not
     * been defined as uncountable in `config/inflector.php`. If this is the
     * case, please report an issue.
     *
     * @param string $str word to check
     *
     * @return  boolean
     *
     * @throws Exception
     */
    public static function uncountable(string $str): bool
    {
        if (static::$uncountable === NULL) {
            // Cache and Make uncountables mirrored
            static::$uncountable = array_fill_keys(\Modseven\Config::instance()->load('inflector')->uncountable, null);
        }

        return isset(static::$uncountable[strtolower($str)]);
    }

    /**
     * Makes a singular word plural.
     *
     * You can also provide the count to make inflection more intelligent.
     * In this case, it will only return the plural value if the count is
     * not one.
     *
     * [!!] Special inflections are defined in `config/inflector.php`.
     *
     * @param string $str word to pluralize
     * @param int|float $count count of thing
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function plural(string $str, $count = NULL): string
    {
        // $count should always be a float
        $count = ($count === NULL) ? 0.0 : (float)$count;

        // Do nothing with singular
        if ($count === 1) {
            return $str;
        }

        // Remove garbage
        $str = trim($str);

        // Cache key name
        $key = 'plural_' . $str . $count;

        // Check uppercase
        $is_uppercase = ctype_upper($str);

        if (isset(static::$cache[$key])) {
            return static::$cache[$key];
        }

        if (self::uncountable($str)) {
            return static::$cache[$key] = $str;
        }

        if (empty(static::$irregular)) {
            // Cache irregular words
            static::$irregular = \Modseven\Config::instance()->load('inflector')->irregular;
        }

        if (isset(static::$irregular[$str])) {
            $str = static::$irregular[$str];
        } elseif (in_array($str, static::$irregular, true)) {
            // Do nothing
        } elseif (preg_match('/[sxz]$/', $str) || preg_match('/[^aeioudgkprt]h$/', $str)) {
            $str .= 'es';
        } elseif (preg_match('/[^aeiou]y$/', $str)) {
            // Change "y" to "ies"
            $str = substr_replace($str, 'ies', -1);
        } else {
            $str .= 's';
        }

        // Convert to uppercase if necessary
        if ($is_uppercase) {
            $str = strtoupper($str);
        }

        // Set the cache and return
        return static::$cache[$key] = $str;
    }

    /**
     * Makes a phrase camel case. Spaces and underscores will be removed.
     *
     * @param string $str phrase to camelize
     * @return  string
     */
    public static function camelize(string $str): string
    {
        $str = 'x' . strtolower(trim($str));
        $str = ucwords(preg_replace('/[\s_]+/', ' ', $str));

        return substr(str_replace(' ', '', $str), 1);
    }

    /**
     * Converts a camel case phrase into a spaced phrase.
     *
     * @param string $str phrase to camelize
     * @param string $sep word separator
     * @return  string
     */
    public static function decamelize(string $str, string $sep = ' '): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1' . $sep . '$2', trim($str)));
    }

    /**
     * Makes a phrase underscored instead of spaced.
     *
     * @param string $str phrase to underscore
     * @return  string
     */
    public static function underscore(string $str): string
    {
        return preg_replace('/\s+/', '_', trim($str));
    }

    /**
     * Makes an underscored or dashed phrase human-readable.
     *
     * @param string $str phrase to make human-readable
     * @return  string
     */
    public static function humanize(string $str): string
    {
        return preg_replace('/[_-]+/', ' ', trim($str));
    }

}
