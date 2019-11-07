<?php
/**
 * A port of [phputf8](http://phputf8.sourceforge.net/) to a unified set
 * of files. Provides multi-byte aware replacement string functions.
 *
 * For UTF-8 support to work correctly, the following requirements must be met:
 *
 * - PCRE needs to be compiled with UTF-8 support (--enable-utf8)
 * - Support for [Unicode properties](http://php.net/manual/reference.pcre.pattern.modifiers.php)
 *   is highly recommended (--enable-unicode-properties)
 * - The [mbstring extension](http://php.net/mbstring) is highly recommended,
 *   but must not be overloading string functions
 *
 * [!!] This file is licensed differently from the rest of Modseven. As a port of
 * [phputf8](http://phputf8.sourceforge.net/), this file is released under the LGPL.
 *
 * @package    Modseven
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */

namespace Modseven;

class UTF8
{
    /**
     * Does the server support UTF-8 natively?
     * @var null|boolean
     */
    public static ?bool $server_utf8 = null;

    /**
     * List of called methods that have had their required file included.
     * @var array
     */
    public static array $called = [];

    /**
     * Recursively cleans arrays, objects, and strings. Removes ASCII control
     * codes and converts to the requested charset while silently discarding
     * incompatible characters.
     *
     * @param mixed $var variable to clean
     * @param string $charset character set, defaults to Modseven::$charset
     * @return  mixed
     */
    public static function clean($var, ?string $charset = NULL)
    {
        if (!$charset) {
            // Use the application character set
            $charset = Core::$charset;
        }

        if (is_array($var) || is_object($var)) {
            foreach ($var as $key => $val) {
                // Recursion!
                $var[self::clean($key)] = self::clean($val);
            }
        } elseif (is_string($var) && $var !== '') {
            // Remove control characters
            $var = self::stripAsciiCtrl($var);

            if (!self::isAscii($var)) {
                // Temporarily save the mb_substitute_character() value into a variable
                $mb_substitute_character = mb_substitute_character();

                // Disable substituting illegal characters with the default '?' character
                mb_substitute_character('none');

                // convert encoding, this is expensive, used when $var is not ASCII
                $var = mb_convert_encoding($var, $charset, $charset);

                // Reset mb_substitute_character() value back to the original setting
                mb_substitute_character($mb_substitute_character);
            }
        }

        return $var;
    }

    /**
     * Strips out device control codes in the ASCII range.
     *
     * @param string $str string to clean
     * @return  string
     */
    public static function stripAsciiCtrl(string $str): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S', '', $str);
    }

    /**
     * Tests whether a string contains only 7-bit ASCII bytes. This is used to
     * determine when to use native functions or UTF-8 functions.
     *
     * @param mixed $str string or array of strings to check
     * @return  boolean
     */
    public static function isAscii($str): bool
    {
        if (is_array($str)) {
            $str = implode($str);
        }

        return !preg_match('/[^\x00-\x7F]/S', $str);
    }

    /**
     * Strips out all non-7bit ASCII bytes.
     *
     * @param string $str string to clean
     * @return  string
     */
    public static function stripNonAscii(string $str): string
    {
        return preg_replace('/[^\x00-\x7F]+/S', '', $str);
    }

    /**
     * Replaces special/accented UTF-8 characters by ASCII-7 "equivalents".
     *
     * @param string $str string to transliterate
     * @param integer $case -1 lowercase only, +1 uppercase only, 0 both cases
     * @return  string
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    public static function transliterateToAscii(string $str, int $case = 0): string
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _transliterateToAscii($str, $case);
    }

    /**
     * Returns the length of the given string. This is a UTF8-aware version
     * of [strlen](http://php.net/strlen).
     *
     * @param string $str string being measured for length
     * @return  integer
     */
    public static function strlen(string $str): int
    {
        if (static::$server_utf8) {
            return mb_strlen($str, Core::$charset);
        }

        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strlen($str);
    }

    /**
     * Finds position of first occurrence of a UTF-8 string. This is a
     * UTF8-aware version of [strpos](http://php.net/strpos).
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $str haystack
     * @param string $search needle
     * @param integer $offset offset from which character in haystack to start searching
     *
     * @return  integer|bool position of needle, FALSE if the needle is not found
     */
    public static function strpos(string $str, string $search, int $offset = 0)
    {
        if (static::$server_utf8) {
            return mb_strpos($str, $search, $offset, Core::$charset);
        }

        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strpos($str, $search, $offset);
    }

    /**
     * Finds position of last occurrence of a char in a UTF-8 string. This is
     * a UTF8-aware version of [strrpos](http://php.net/strrpos).
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $str haystack
     * @param string $search needle
     * @param integer $offset offset from which character in haystack to start searching
     *
     * @return  integer|bool position of needle, FALSE if the needle is not found
     */
    public static function strrpos(string $str, string $search, int $offset = 0)
    {
        if (static::$server_utf8) {
            return mb_strrpos($str, $search, $offset, Core::$charset);
        }

        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strrpos($str, $search, $offset);
    }

    /**
     * Returns part of a UTF-8 string. This is a UTF8-aware version
     * of [substr](http://php.net/substr).
     *
     * @param string $str input string
     * @param integer $offset offset
     * @param integer $length length limit
     * @return  string
     * @author  Chris Smith <chris@jalakai.co.uk>
     */
    public static function substr(string $str, int $offset, ?int $length = NULL): string
    {
        if (static::$server_utf8) {
            return ($length === null) ? mb_substr($str, $offset, mb_strlen($str), Core::$charset) : mb_substr($str,
                $offset, $length, Core::$charset);
        }

        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _substr($str, $offset, $length);
    }

    /**
     * Replaces text within a portion of a UTF-8 string. This is a UTF8-aware
     * version of [substr_replace](http://php.net/substr_replace).
     *
     * @param string $str input string
     * @param string $replacement replacement string
     * @param integer $offset Offset
     * @param integer $length Length
     * @return  string
     * @author  Harry Fuecks <hfuecks@gmail.com>
     */
    public static function substrReplace(string $str, string $replacement, int $offset, ?int $length = NULL): string
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _substrReplace($str, $replacement, $offset, $length);
    }

    /**
     * Makes a UTF-8 string lowercase. This is a UTF8-aware version
     * of [strtolower](http://php.net/strtolower).
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     *
     * @param string $str mixed case string
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function strtolower(string $str): string
    {
        if (static::$server_utf8) {
            return mb_strtolower($str, Core::$charset);
        }

        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strtolower($str);
    }

    /**
     * Makes a UTF-8 string uppercase. This is a UTF8-aware version
     * of [strtoupper](http://php.net/strtoupper).
     *
     * @author  Andreas Gohr <andi@splitbrain.org>
     *
     * @param string $str mixed case string
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function strtoupper(string $str): string
    {
        if (static::$server_utf8) {
            return mb_strtoupper($str, Core::$charset);
        }

        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strtoupper($str);
    }

    /**
     * Makes a UTF-8 string's first character uppercase. This is a UTF8-aware
     * version of [ucfirst](http://php.net/ucfirst).
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $str mixed case string
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function ucfirst(string $str): string
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _ucfirst($str);
    }

    /**
     * Makes the first character of every word in a UTF-8 string uppercase.
     * This is a UTF8-aware version of [ucwords](http://php.net/ucwords).
     *
     * @param string $str mixed case string
     * @return  string
     * @author  Harry Fuecks <hfuecks@gmail.com>
     */
    public static function ucwords(string $str): string
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _ucwords($str);
    }

    /**
     * Case-insensitive UTF-8 string comparison. This is a UTF8-aware version
     * of [strcasecmp](http://php.net/strcasecmp).
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $str1 string to compare
     * @param string $str2 string to compare
     *
     * @return  integer less than 0 if str1 is less than str2, greater than 0 if str1 is greater than str2,0 if they are equal
     *
     * @throws Exception
     */
    public static function strcasecmp(string $str1, string $str2): int
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strcasecmp($str1, $str2);
    }

    /**
     * Returns a string or an array with all occurrences of search in subject
     * (ignoring case) and replaced with the given replace value. This is a
     * UTF8-aware version of [str_ireplace](http://php.net/str_ireplace).
     *
     * [!!] This function is very slow compared to the native version. Avoid
     * using it when possible.
     *
     * @author  Harry Fuecks <hfuecks@gmail.com
     *
     * @param string|array $search text to replace
     * @param string|array $replace replacement text
     * @param string|array $str subject text
     * @param integer $count number of matched and replaced needles will be returned via this parameter which is passed by reference
     *
     * @return  string|array  if the input was a string, if the input was an array
     *
     * @throws Exception
     */
    public static function strIreplace($search, $replace, $str, ?int & $count = NULL)
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strIreplace($search, $replace, $str, $count);
    }

    /**
     * Case-insensitive UTF-8 version of strstr. Returns all of input string
     * from the first occurrence of needle to the end. This is a UTF8-aware
     * version of [stristr](http://php.net/stristr).
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $str input string
     * @param string $search needle
     *
     * @return  string|false  matched substring if found, if the substring was not found
     *
     * @throws Exception
     */
    public static function stristr(string $str, string $search)
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _stristr($str, $search);
    }

    /**
     * Finds the length of the initial segment matching mask. This is a
     * UTF8-aware version of [strspn](http://php.net/strspn).
     *
     * @param string $str input string
     * @param string $mask mask for search
     * @param integer $offset start position of the string to examine
     * @param integer $length length of the string to examine
     * @return  integer length of the initial segment that contains characters in the mask
     * @author  Harry Fuecks <hfuecks@gmail.com>
     */
    public static function strspn(string $str, string $mask, ?int $offset = NULL, ?int $length = NULL): int
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strspn($str, $mask, $offset, $length);
    }

    /**
     * Finds the length of the initial segment not matching mask. This is a
     * UTF8-aware version of [strcspn](http://php.net/strcspn).
     *
     * @param string $str input string
     * @param string $mask mask for search
     * @param integer $offset start position of the string to examine
     * @param integer $length length of the string to examine
     * @return  integer length of the initial segment that contains characters not in the mask
     * @author  Harry Fuecks <hfuecks@gmail.com>
     */
    public static function strcspn(string $str, string $mask, ?int $offset = NULL, ?int $length = NULL): int
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strcspn($str, $mask, $offset, $length);
    }

    /**
     * Pads a UTF-8 string to a certain length with another string. This is a
     * UTF8-aware version of [str_pad](http://php.net/str_pad).
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $str input string
     * @param integer $final_str_length desired string length after padding
     * @param string $pad_str string to use as padding
     * @param int $pad_type padding type: STR_PAD_RIGHT, STR_PAD_LEFT, or STR_PAD_BOTH
     *
     * @return  string
     *
     * @throws Exception
     */
    public static function strPad(string $str, int $final_str_length, string $pad_str = ' ', int $pad_type = STR_PAD_RIGHT): string
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strPad($str, $final_str_length, $pad_str, $pad_type);
    }

    /**
     * Converts a UTF-8 string to an array. This is a UTF8-aware version of
     * [str_split](http://php.net/str_split).
     *
     * @param string $str input string
     * @param integer $split_length maximum length of each chunk
     * @return  array
     * @author  Harry Fuecks <hfuecks@gmail.com>
     */
    public static function strSplit(string $str, int $split_length = 1): array
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strSplit($str, $split_length);
    }

    /**
     * Reverses a UTF-8 string. This is a UTF8-aware version of [strrev](http://php.net/strrev).
     *
     * @param string $str string to be reversed
     * @return  string
     * @author  Harry Fuecks <hfuecks@gmail.com>
     */
    public static function strrev(string $str): string
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _strrev($str);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning and
     * end of a string. This is a UTF8-aware version of [trim](http://php.net/trim).
     *
     * @param string $str input string
     * @param string $charlist string of characters to remove
     * @return  string
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    public static function trim(string $str, ?string $charlist = NULL): string
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _trim($str, $charlist);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the beginning of
     * a string. This is a UTF8-aware version of [ltrim](http://php.net/ltrim).
     *
     * @param string $str input string
     * @param string $charlist string of characters to remove
     * @return  string
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    public static function ltrim(string $str, ?string $charlist = NULL): string
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _ltrim($str, $charlist);
    }

    /**
     * Strips whitespace (or other UTF-8 characters) from the end of a string.
     * This is a UTF8-aware version of [rtrim](http://php.net/rtrim).
     *
     * @param string $str input string
     * @param string $charlist string of characters to remove
     * @return  string
     * @author  Andreas Gohr <andi@splitbrain.org>
     */
    public static function rtrim(string $str, ?string $charlist = NULL): string
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _rtrim($str, $charlist);
    }

    /**
     * Returns the unicode ordinal for a character. This is a UTF8-aware
     * version of [ord](http://php.net/ord).
     *
     * @author  Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $chr UTF-8 encoded character
     *
     * @return  integer
     *
     * @throws Exception
     */
    public static function ord(string $chr): int
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _ord($chr);
    }

    /**
     * Takes an UTF-8 string and returns an array of ints representing the Unicode characters.
     * Astral planes are supported i.e. the ints in the output can be > 0xFFFF.
     * Occurrences of the BOM are ignored. Surrogates are not allowed.
     *
     * The Original Code is Mozilla Communicator client code.
     * The Initial Developer of the Original Code is Netscape Communications Corporation.
     * Portions created by the Initial Developer are Copyright (C) 1998 the Initial Developer.
     * Ported to PHP by Henri Sivonen <hsivonen@iki.fi>, see <http://hsivonen.iki.fi/php-utf8/>
     * Slight modifications to fit with phputf8 library by Harry Fuecks <hfuecks@gmail.com>
     *
     * @param string $str UTF-8 encoded string
     *
     * @return  array|false   unicode code points, false on invalid string
     *
     * @throws Exception
     */
    public static function toUnicode(string $str)
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _toUnicode($str);
    }

    /**
     * Takes an array of ints representing the Unicode characters and returns a UTF-8 string.
     * Astral planes are supported i.e. the ints in the input can be > 0xFFFF.
     * Occurrences of the BOM are ignored. Surrogates are not allowed.
     *
     * The Original Code is Mozilla Communicator client code.
     * The Initial Developer of the Original Code is Netscape Communications Corporation.
     * Portions created by the Initial Developer are Copyright (C) 1998 the Initial Developer.
     * Ported to PHP by Henri Sivonen <hsivonen@iki.fi>, see http://hsivonen.iki.fi/php-utf8/
     * Slight modifications to fit with phputf8 library by Harry Fuecks <hfuecks@gmail.com>.
     *
     * @param array $arr unicode code points representing a string
     *
     * @return  string|false  utf8 string of characters, false if code point cannot be found
     *
     * @throws Exception
     */
    public static function fromUnicode(array $arr)
    {
        if (!isset(static::$called[__FUNCTION__])) {
            require Core::findFile('utf8', __FUNCTION__);

            // Function has been called
            static::$called[__FUNCTION__] = TRUE;
        }

        return _fromUnicode($arr);
    }

}

if (UTF8::$server_utf8 === NULL) {
    // Determine if this server supports UTF-8 natively
    UTF8::$server_utf8 = extension_loaded('mbstring');
}
