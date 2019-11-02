<?php
/**
 * UTF8::strspn
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

function _strspn($str, $mask, $offset = NULL, $length = NULL)
{
    if ($str === '' || $mask === '') {
        return 0;
    }

    if (UTF8::is_ascii($str) && UTF8::is_ascii($mask)) {
        return ($offset === null) ? strspn($str, $mask) : (($length === null) ? strspn($str, $mask,
            $offset) : strspn($str, $mask, $offset, $length));
    }

    if ($offset !== NULL || $length !== NULL) {
        $str = UTF8::substr($str, $offset, $length);
    }

    // Escape these characters:  - [ ] . : \ ^ /
    // The . and : are escaped to prevent possible warnings about POSIX regex elements
    $mask = preg_replace('#[-[\].:\\\\^/]#', '\\\\$0', $mask);
    preg_match('/^[^' . $mask . ']+/u', $str, $matches);

    return isset($matches[0]) ? UTF8::strlen($matches[0]) : 0;
}
