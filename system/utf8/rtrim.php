<?php
/**
 * UTF8::rtrim
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

function _rtrim($str, $charlist = NULL)
{
    if ($charlist === NULL) {
        return rtrim($str);
    }

    if (UTF8::is_ascii($charlist)) {
        return rtrim($str, $charlist);
    }

    $charlist = preg_replace('#[-\[\]:\\\\^/]#', '\\\\$0', $charlist);

    return preg_replace('/[' . $charlist . ']++$/uD', '', $str);
}
