<?php
/**
 * Class for formatting REST-Response bodies as JSON
 *
 * @copyright  (c) 2016 - 2020 Koseven Team
 * @copyright  (c) since  2020 Modseven Team
 *
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\REST\Formatter;

use Modseven\REST\Exception;
use Modseven\REST\Formatter;

class JSON extends Formatter
{
    /**
     * Holds content type for this class
     * @var string
     */
    protected string $_contentType = 'application/json';

    /**
     * Holds extension type for this class, used for sending files
     * @var string
     */
    protected string $_extensionType = 'json';

    /**
     * Format function
     *
     * @throws Exception
     *
     * @return string
     */
    public function format() : string
    {
        try
        {
            $body = json_encode($this->_body,
                                JSON_THROW_ON_ERROR
                                |JSON_FORCE_OBJECT
                                |JSON_NUMERIC_CHECK
                                |JSON_PRESERVE_ZERO_FRACTION
                                |JSON_UNESCAPED_UNICODE
            );
        }
        catch(\Exception $e)
        {
            throw new Exception($e->getMessage(), NULL, $e->getCode(), $e);
        }

        return $body;
    }

}