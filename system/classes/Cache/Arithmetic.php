<?php
/**
 * Modseven Cache Arithmetic Interface, for basic cache integer based
 * arithmetic, addition and subtraction
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Cache;

interface Arithmetic
{

    /**
     * Increments a given value by the step value supplied.
     * Useful for shared counters and other persistent integer based
     * tracking.
     *
     * @param string    id of cache entry to increment
     * @param int       step value to increment by
     * @return  integer|bool
     */
    public function increment(string $id, int $step = 1);

    /**
     * Decrements a given value by the step value supplied.
     * Useful for shared counters and other persistent integer based
     * tracking.
     *
     * @param string    id of cache entry to decrement
     * @param int       step value to decrement by
     * @return  integer|bool
     */
    public function decrement(string $id, int $step = 1);

}