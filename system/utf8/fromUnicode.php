<?php
/**
 * UTF8::from_unicode
 *
 * @package    Modseven
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */

use Modseven\UTF8\Exception;

/**
 * UTF8 form_unicode function
 *
 * @param array $arr
 *
 * @return false|string
 *
 * @throws Exception
 */
function _fromUnicode(array $arr)
{
    ob_start();

    foreach (array_keys($arr) as $k) {
        // ASCII range (including control chars)
        if (($arr[$k] >= 0) && ($arr[$k] <= 0x007f)) {
            echo chr($arr[$k]);
        } // 2 byte sequence
        elseif ($arr[$k] <= 0x07ff) {
            echo chr(0xc0 | ($arr[$k] >> 6));
            echo chr(0x80 | ($arr[$k] & 0x003f));
        } // Byte order mark (skip)
        elseif ($arr[$k] == 0xFEFF) {
            // nop -- zap the BOM
        } // Test for illegal surrogates
        elseif ($arr[$k] >= 0xD800 && $arr[$k] <= 0xDFFF) {
            // Found a surrogate
            throw new Exception("UTF8::from_unicode: Illegal surrogate at index: ':index', value: ':value'", [
                ':index' => $k,
                ':value' => $arr[$k],
            ]);
        } // 3 byte sequence
        elseif ($arr[$k] <= 0xffff) {
            echo chr(0xe0 | ($arr[$k] >> 12));
            echo chr(0x80 | (($arr[$k] >> 6) & 0x003f));
            echo chr(0x80 | ($arr[$k] & 0x003f));
        } // 4 byte sequence
        elseif ($arr[$k] <= 0x10ffff) {
            echo chr(0xf0 | ($arr[$k] >> 18));
            echo chr(0x80 | (($arr[$k] >> 12) & 0x3f));
            echo chr(0x80 | (($arr[$k] >> 6) & 0x3f));
            echo chr(0x80 | ($arr[$k] & 0x3f));
        } // Out of range
        else {
            throw new Exception("UTF8::from_unicode: Codepoint out of Unicode range at index: ':index', value: ':value'", [
                ':index' => $k,
                ':value' => $arr[$k],
            ]);
        }
    }

    $result = ob_get_clean();
    ob_end_clean();
    return $result;
}
