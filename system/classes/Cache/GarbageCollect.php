<?php
/**
 * Garbage Collection interface for caches that have no GC methods
 * of their own, such as File Cache and SQLite Cache. Memory based
 * cache systems clean their own caches periodically.
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Cache;

interface GarbageCollect
{
    /**
     * Garbage collection method that cleans any expired
     * cache entries from the cache.
     */
    public function garbage_collect() : void;
}