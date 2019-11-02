<?php
/**
 * UTF8::strlen
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

function _strlen($str)
{
    if (UTF8::is_ascii($str)) {
        return strlen($str);
    }

    return strlen(utf8_decode($str));
}
