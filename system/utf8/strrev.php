<?php
/**
 * UTF8::strrev
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

function _strrev($str)
{
    if (UTF8::isAscii($str)) {
        return strrev($str);
    }

    preg_match_all('/./us', $str, $matches);
    return implode('', array_reverse($matches[0]));
}
