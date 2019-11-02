<?php
/**
 * Stream driver performs external requests using php
 * sockets.
 *
 * This is the default driver for all external requests.
 *
 * @package        Modseven\Request
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 *
 */

namespace Modseven\Request\Client;

use Modseven\Request;
use Modseven\Request\Exception;
use Modseven\Response;

class Stream extends External
{
    /**
     * Sends the HTTP message [Request] to a remote server and processes
     * the response.
     *
     * @param Request $request request to send
     * @param Response $response response to send
     *
     * @return  Response
     * @throws Exception
     *
     */
    public function _send_message(Request $request, Response $response): Response
    {
        // Calculate stream mode
        $mode = ($request->method() === \Modseven\HTTP\Request::GET) ? 'r' : 'r+';

        // Process cookies
        if ($cookies = $request->cookie()) {
            $request->headers('cookie', http_build_query($cookies, NULL, '; '));
        }

        // Get the message body
        $body = $request->body();

        // Set the content length and form-urlencoded
        if ($body) {
            $request->headers('content-length', (string)strlen($body));
            $request->headers('content-type', 'application/x-www-form-urlencoded');
        }

        [$protocol] = explode('/', $request->protocol(), 2);

        // Create the context
        $options = [
            strtolower($protocol) => [
                'method' => $request->method(),
                'header' => (string)$request->headers(),
                'content' => $body
            ]
        ];

        // Create the context stream
        $context = stream_context_create($options);

        // Set options
        if (!empty($this->_options)) {
            stream_context_set_option($context, $this->_options);
        }

        $uri = $request->uri();

        if ($query = $request->query()) {
            $uri .= '?' . http_build_query($query, NULL, '&');
        }

        // Throws an Exception if you try to write smth. but requested stream is not write-able or unavailable
        try {
            $stream = fopen($uri, $mode, FALSE, $context);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        $meta_data = stream_get_meta_data($stream);

        // Get the HTTP response code
        $http_response = array_shift($meta_data['wrapper_data']);

        // Fetch respone protocol and status
        preg_match_all('/(\w+/\d\.\d) (\d{3})/', $http_response, $matches);

        $protocol = $matches[1][0];
        $status = (int)$matches[2][0];

        // Get any existing response headers
        $response_header = $response->headers();

        // Process headers
        array_map([$response_header, 'parse_header_string'], [], $meta_data['wrapper_data']);

        // Build the response
        $response->status($status)->protocol($protocol)->body(stream_get_contents($stream));

        // Close the stream after use
        fclose($stream);

        return $response;
    }

}
