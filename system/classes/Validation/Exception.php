<?php
/**
 * @package    Modseven
 * @category   Exceptions
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Validation\Exception;

use Modseven\Validation;

class Exception extends \Modseven\Exception
{
    /**
     * Validation instance
     * @var Validation
     */
    public Validation $array;

    /**
     * @param Validation      $array    Validation object
     * @param string          $message  error message
     * @param array           $values   translation variables
     * @param int             $code     the exception code
     * @param null|\Exception $previous Previous Exception
     */
    public function __construct(Validation $array, string $message = 'Failed to validate array', ?array $values = NULL, int $code = 0, \Exception $previous = NULL)
    {
        $this->array = $array;

        parent::__construct($message, $values, $code, $previous);
    }

}
