<?php
/**
 * Internationalization (i18n) class. Provides language loading and translation
 * methods without dependencies on [gettext](http://php.net/gettext).
 * Typically this class would never be used directly, but used via the __()
 * function, which loads the message and replaces parameters:
 *
 *     // Display a translated message in APPATH
 *     echo __('Hello, world');
 *
 *       // Display a translated message in SYSPATH and MODPATH
 *       echo I18n::get('Hello, world');
 *
 *     // With parameter replacement in APPATH
 *     echo __('Hello, :user', [':user' => $username]);
 *
 *     // With parameter replacement in SYSPATH nad MODPATH
 *       echo I18n::get(['Hello, :user', [':user' => $username]]);
 *
 * @package    Modseven
 * @category   Base
 *
 * @copyright  (c) 2008 - 2016 Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

class I18n
{
    /**
     * Target language: en-us, es-es, zh-cn, etc
     * @var  string
     */
    public static string $lang = 'en-us';

    /**
     * Source language: en-us, es-es, zh-cb, etc
     * @var string
     */
    public static string $source = 'en-us';

    /**
     * Cache of loaded languages
     * @var array
     */
    protected static array $_cache = [];

    /**
     * Get and set the target language.
     *
     * @param string $lang New target language
     *
     * @return  string
     */
    public static function lang(?string $lang = NULL): string
    {
        if ($lang && $lang !== static::$lang) {
            static::$lang = strtolower(str_replace([' ', '_'], '-', $lang));
        }

        return static::$lang;
    }

    /**
     * Returns translation of a string. If no translation exists, the original
     * string will be returned. No parameters are replaced.
     *
     * @param string|array $string Text to translate or array [text, values]
     * @param string $lang Target Language
     * @param string $source Source Language
     * @return  string
     */
    public static function get($string, ?string $lang = NULL, ?string $source = NULL): string
    {
        $values = [];

        // Check if $string is array [text, values]
        if (Arr::is_array($string)) {
            if (isset($string[1]) && Arr::is_array($string[1])) {
                $values = $string[1];
            }
            $string = $string[0];
        }

        // Set Target Language if not set
        if (!$lang) {
            // Use the global target language
            $lang = static::$lang;
        }

        // Set source Language if not set
        if (!$source) {
            // Use the global source language
            $source = static::$source;
        }

        // Load Table only if Source language does not match target language
        if ($source !== $lang) {
            // Load the translation table for this language
            $table = static::load($lang);

            // Return the translated string if it exists
            $string = $table[$string] ?? $string;
        }

        return empty($values) ? $string : strtr($string, $values);
    }

    /**
     * Returns the translation table for a given language.
     *
     * @param string $lang language to load
     * @return  array
     */
    public static function load(string $lang): array
    {
        if (isset(static::$_cache[$lang])) {
            return static::$_cache[$lang];
        }

        // New translation table
        $table = [[]];

        // Split the language: language, region, locale, etc
        $parts = explode('-', $lang);

        // Loop through Paths
        foreach ([$parts[0], implode(DIRECTORY_SEPARATOR, $parts)] as $path) {
            // Load files
            $files = Core::find_file('i18n', $path);

            // Loop through files
            if (!empty($files)) {
                $t = [[]];
                foreach ($files as $file) {
                    // Merge the language strings into the sub table
                    $t[] = Core::load($file);
                }
                $table[] = $t;
            }
        }

        $table = array_merge(...array_merge(...$table));

        // Cache the translation table locally
        return static::$_cache[$lang] = $table;
    }
}

if (!function_exists('__')) {
    /**
     * Modseven translation/internationalization function. The PHP function
     * [strtr](http://php.net/strtr) is used for replacing parameters.
     *
     * @param string $string Text to translate
     * @param array $values Values to replace in the translated text
     * @param string $lang Source language
     *
     * @return  string
     */
    function __(string $string, ?array $values = NULL, ?string $lang = NULL): string
    {
        return I18n::get($values ? [$string, $values] : $string, NULL, $lang);
    }
}
