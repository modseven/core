<?php
/**
 * The HTTP Interaction interface providing the core HTTP methods that
 * should be implemented by any HTTP request or response class.
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

interface Message
{

    /**
     * Gets or sets the HTTP protocol. The standard protocol to use
     * is `HTTP/1.1`.
     *
     * @param string $protocol Protocol to set to the request/response
     * @return  mixed
     */
    public function protocol(?string $protocol = NULL);

    /**
     * Gets or sets HTTP headers to the request or response. All headers
     * are included immediately after the HTTP protocol definition during
     * transmission. This method provides a simple array or key/value
     * interface to the headers.
     *
     * @param mixed $key Key or array of key/value pairs to set
     * @param string $value Value to set to the supplied key
     * @return  mixed
     */
    public function headers($key = NULL, ?string $value = NULL);

    /**
     * Gets or sets the HTTP body to the request or response. The body is
     * included after the header, separated by a single empty new line.
     *
     * @param string $content Content to set to the object
     * @return  string|void
     */
    public function body(?string $content = NULL);

    /**
     * Renders the HTTP_Interaction to a string, producing
     *
     *  - Protocol
     *  - Headers
     *  - Body
     *
     * @return  string
     */
    public function render(): string;

}
