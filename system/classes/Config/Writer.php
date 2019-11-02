<?php
/**
 * Interface for config writers
 *
 * Specifies the methods that a config writer must implement
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Config;

interface Writer extends Source
{
    /**
     * Writes the passed config for $group
     *
     * @param string $group     The config group
     * @param string $key       The config key to write to
     * @param array  $config    The configuration to write
     *
     * @return bool
     */
    public function write(string $group, string $key, array $config): bool;

}
