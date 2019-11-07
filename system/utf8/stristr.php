<?php
/**
 * UTF8::stristr
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
 * UTF8 stristr
 *
 * @param $str
 * @param $search
 *
 * @return bool|string
 *
 * @throws \Modseven\Exception
 */
function _stristr($str, $search)
{
    if (UTF8::isAscii($str) && UTF8::isAscii($search)) {
        return stristr($str, $search);
    }

    if ($search === '') {
        return $str;
    }

    $str_lower = UTF8::strtolower($str);
    $search_lower = UTF8::strtolower($search);

    preg_match('/^(.*?)' . preg_quote($search_lower, '/') . '/s', $str_lower, $matches);

    if (isset($matches[1])) {
        return substr($str, strlen($matches[1]));
    }

    return FALSE;
}
