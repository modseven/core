<?php
/**
 * UTF8::ord
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
 * Converts an ASCII value to an integer
 *
 * @param string $chr ASCII Character
 *
 * @return int
 *
 * @throws Exception
 */
function _ord(string $chr) : int
{
    $ord0 = ord($chr);

    if ($ord0 >= 0 && $ord0 <= 127) {
        return $ord0;
    }

    if (!isset($chr[1])) {
        throw new Exception('Short sequence - at least 2 bytes expected, only 1 seen');
    }

    $ord1 = ord($chr[1]);

    if ($ord0 >= 192 && $ord0 <= 223) {
        return ($ord0 - 192) * 64 + ($ord1 - 128);
    }

    if (!isset($chr[2])) {
        throw new Exception('Short sequence - at least 3 bytes expected, only 2 seen');
    }

    $ord2 = ord($chr[2]);

    if ($ord0 >= 224 && $ord0 <= 239) {
        return ($ord0 - 224) * 4096 + ($ord1 - 128) * 64 + ($ord2 - 128);
    }

    if (!isset($chr[3])) {
        throw new Exception('Short sequence - at least 4 bytes expected, only 3 seen');
    }

    $ord3 = ord($chr[3]);

    if ($ord0 >= 240 && $ord0 <= 247) {
        return ($ord0 - 240) * 262144 + ($ord1 - 128) * 4096 + ($ord2 - 128) * 64 + ($ord3 - 128);
    }

    if (!isset($chr[4])) {
        throw new Exception('Short sequence - at least 5 bytes expected, only 4 seen');
    }

    $ord4 = ord($chr[4]);

    if ($ord0 >= 248 && $ord0 <= 251) {
        return ($ord0 - 248) * 16777216 + ($ord1 - 128) * 262144 + ($ord2 - 128) * 4096 + ($ord3 - 128) * 64 + ($ord4 - 128);
    }

    if (!isset($chr[5])) {
        throw new Exception('Short sequence - at least 6 bytes expected, only 5 seen');
    }

    if ($ord0 >= 252 && $ord0 <= 253) {
        return ($ord0 - 252) * 1073741824 + ($ord1 - 128) * 16777216 + ($ord2 - 128) * 262144 + ($ord3 - 128) * 4096 +
               ($ord4 - 128) * 64 + (ord($chr[5]) - 128);
    }

    if ($ord0 >= 254 && $ord0 <= 255) {
        throw new Exception("Invalid UTF-8 with surrogate ordinal ':ordinal'", [
            ':ordinal' => $ord0,
        ]);
    }

    throw new Exception('Could not convert the ASCII character to an integer.');
}
