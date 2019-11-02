<?php
/**
 * UTF8::strcasecmp
 *
 * @package    Modseven
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @copyright  (c) 2005 Harry Fuecks
 * @license    http://www.gnu.org/licenses/old-licenses/lgpl-2.1.txt
 */

use Modseven\UTF8;

/**
 * UTF8 strcasecmp
 *
 * @param $str1
 * @param $str2
 *
 * @return int
 *
 * @throws \Modseven\Exception
 */
function _strcasecmp($str1, $str2)
{
    if (UTF8::is_ascii($str1) && UTF8::is_ascii($str2)) {
        return strcasecmp($str1, $str2);
    }

    $str1 = UTF8::strtolower($str1);
    $str2 = UTF8::strtolower($str2);
    return strcmp($str1, $str2);
}
