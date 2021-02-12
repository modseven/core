<?php
/**
 * Modseven Cache InvalidArgument Exception
 * When an invalid argument is passed, this Exception is thrown
 *
 * Throw this on Cache Error
 *
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Cache;

class InvalidArgumentException extends \Modseven\Exception implements \Psr\SimpleCache\InvalidArgumentException {}
