<?php
/**
 * Interface for formatting Modseven REST Requests.
 *
 * This interface needs to be extended from every REST-Formatter
 *
 * The following formatter come shipped with Modseven:
 *  - JSON
 *  - XML
 *  - HTML
 *
 * @copyright  (c) 2016 - 2020 Koseven Team
 * @copyright  (c) since  2020 Modseven Team
 *
 * @license        https://koseven.ga/LICENSE
 */

namespace Modseven\REST;

use Modseven\HTTP\Request;
use Modseven\HTTP\Response;

abstract class Formatter {

    /**
     * Default Output Format
     * @var string
     */
    public static string $default_formatter = '\\Modseven\\REST\\Formatter\\HTML';

    /**
     * Holds an instance of the request class
     * @var Request
     */
    protected Request $_request;

    /**
     * Holds an instance of the response class
     * @var Response
     */
    protected Response $_response;

    /**
     * Holds the response body
     * @var array|string
     */
    protected $_body;

    /**
     * Holds content type for this class
     * @var string
     */
    protected string $_contentType = 'text/html';

    /**
     * Holds extension type for this class, used for sending files
     * @var string
     */
    protected string $_extensionType = 'html';

    /**
     * Factory Method for REST Formatter
     *
     * @param Request  $request  Request Class
     * @param Response $response Response Class
     *
     * @throws Exception
     *
     * @return self
     */
    public static function factory(Request $request, Response $response) : self
    {
        // Check if format is set by route, otherwise use default
        if ($request->format() === NULL)
        {
            $request->format(static::$default_formatter);
        }

        // Check if formatter Exists
        $formatter = $request->format();
        if ( ! class_exists($formatter))
        {
            throw new Exception('Formatter :formatter does not exist.', [
                ':formatter' => $formatter
            ]);
        }

        $formatter = new $formatter($request, $response);

        // Check if client extends Request_Client_External
        if ( ! $formatter instanceof self)
        {
            throw new Exception(':formatter is not a valid REST formatter.', [
                ':formatter' => get_class($formatter)
            ]);
        }

        // Set response content type by format used
        $response->headers('Content-Type', $formatter->getContentType());

        return $formatter;
    }

    /**
     * Constructor.
     *
     * @param Request  $request
     * @param Response $response
     */
    public function __construct(Request $request, Response $response)
    {
        $this->_request = $request;
        $this->_response = $response;

        // Make sure body is an array
        $body = $response->body();
        if (is_string($body))
        {
            $body = [
                'body' => $body
            ];
        }

        $this->_body = $body;
    }

    /**
     * Returns content type which should be used for the formatter
     * @return string
     */
    public function getContentType() : string
    {
        return $this->_contentType;
    }

    /**
     * Returns extension type which is used to send files
     * @return string
     */
    public function getExtensionType() : string
    {
        return $this->_extensionType;
    }

    /**
     * Function for formatting the body
     *
     * @return string
     */
    abstract public function format() : string;

}