<?php
/**
 * A HTTP Response specific interface that adds the methods required
 * by HTTP responses. Over and above [Modseven_HTTP_Interaction], this
 * interface provides status.
 *
 * @package    Modseven
 * @category   HTTP
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\HTTP;

interface Response extends Message
{

    /**
     * Sets or gets the HTTP status from this response.
     *
     * @param integer $code Status to set to this response
     * @return  mixed
     */
    public function status(int $code = NULL);

}
