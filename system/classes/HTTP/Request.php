<?php
/**
 * A HTTP Request specific interface that adds the methods required
 * by HTTP requests. Over and above [Modseven_HTTP_Interaction], this
 * interface provides method, uri, get and post methods.
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

interface Request extends Message
{

    // HTTP Methods
    public const GET = 'GET';
    public const POST = 'POST';
    public const PATCH = 'PATCH';
    public const PUT = 'PUT';
    public const DELETE = 'DELETE';
    public const HEAD = 'HEAD';
    public const OPTIONS = 'OPTIONS';
    public const TRACE = 'TRACE';
    public const CONNECT = 'CONNECT';

    /**
     * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
     * traditional CRUD applications.
     *
     * @param string $method Method to use for this request
     * @return  mixed
     */
    public function method(?string $method = NULL);

    /**
     * Gets the URI of this request, optionally allows setting
     * of [Route] specific parameters during the URI generation.
     * If no parameters are passed, the request will use the
     * default values defined in the Route.
     *
     * @return  string
     */
    public function uri(): string;

    /**
     * Gets or sets HTTP query string.
     *
     * @param mixed $key Key or key value pairs to set
     * @param string $value Value to set to a key
     * @return  mixed
     */
    public function query($key = NULL, ?string $value = NULL);

    /**
     * Gets or sets HTTP POST parameters to the request.
     *
     * @param mixed $key Key or key value pairs to set
     * @param string $value Value to set to a key
     * @return  mixed
     */
    public function post($key = NULL, ?string $value = NULL);

}
