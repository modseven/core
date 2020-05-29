<?php
/**
 * Modseven Cache Tagging Interface
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Cache\Driver;

interface Tagging
{
    /**
     * Set a value based on an id. Optionally add tags.
     *
     * Note : Some caching engines do not support
     * tagging
     *
     * @param   string   $key       Cache Key
     * @param   mixed    $data      data
     * @param   integer  $lifetime  lifetime [Optional]
     * @param   array    $tags      tags [Optional]
     *
     * @return  bool
     */
    public function setWithTags(string $key, $data, array $tags, ?int $lifetime) : bool;

    /**
     * Delete cache entries based on a tag
     *
     * @param   string  $tag  tag
     *
     * @return bool
     */
    public function deleteTag(string $tag) : bool;

    /**
     * Find cache entries based on a tag
     *
     * @param   string  $tag  tag
     *
     * @return  array
     */
    public function getWithTag(string $tag) : array;
}