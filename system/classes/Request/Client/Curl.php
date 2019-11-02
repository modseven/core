<?php
/**
 * Curl driver performs external requests using the
 * php-curl extension.
 *
 * NOTE: This driver is not used by default. To use it as default call:
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

class Curl extends External
{

    /**
     * Sends the HTTP message [Request] to a remote server and processes
     * the response.
     *
     * @param Request $request request to send
     * @param Response $response response to send
     *
     * @return  Response
     *
     * @throws Exception
     * @throws \Modseven\Exception
     */
    public function _send_message(Request $request, Response $response): Response
    {
        $options = [];

        // Set the request method
        $options = $this->_set_curl_request_method($request, $options);

        // Set the request body. This is perfectly legal in CURL even
        // if using a request other than POST. PUT does support this method
        // and DOES NOT require writing data to disk before putting it, if
        // reading the PHP docs you may have got that impression. SdF
        // This will also add a Content-Type: application/x-www-form-urlencoded header unless you override it
        if ($body = $request->body()) {
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        // Process headers
        if ($headers = $request->headers()) {
            $http_headers = [];

            foreach ($headers as $key => $value) {
                $http_headers[] = $key . ': ' . $value;
            }

            $options[CURLOPT_HTTPHEADER] = $http_headers;
        }

        // Process cookies
        if ($cookies = $request->cookie()) {
            $options[CURLOPT_COOKIE] = http_build_query($cookies, NULL, '; ');
        }

        // Get any existing response headers
        $response_header = $response->headers();

        // Implement the standard parsing parameters
        $options[CURLOPT_HEADERFUNCTION] = [$response_header, 'parse_header_string'];
        $this->_options[CURLOPT_RETURNTRANSFER] = TRUE;
        $this->_options[CURLOPT_HEADER] = FALSE;

        // Apply any additional options set to
        $options += $this->_options;

        $uri = $request->uri();

        if ($query = $request->query()) {
            $uri .= '?' . http_build_query($query, NULL, '&');
        }

        // Open a new remote connection
        $curl = curl_init($uri);

        // Set connection options - Throws an Exception if options are invalid
        try {
            curl_setopt_array($curl, $options);
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

        // Get the response body
        $body = curl_exec($curl);

        // Get the response information
        $code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($body === FALSE) {
            $error = curl_error($curl);
        }

        // Close the connection
        curl_close($curl);

        if (isset($error)) {
            throw new Exception(
                'Error fetching remote :url [ status :code ] :error',
                [':url' => $request->url(), ':code' => $code, ':error' => $error]
            );
        }

        // Build the response
        $response->status($code)->body($body);

        return $response;
    }

    /**
     * Sets the appropriate curl request options. Uses the responding option
     * for POST or CURLOPT_CUSTOMREQUEST otherwise
     *
     * @param Request $request
     * @param array $options
     *
     * @return array
     */
    public function _set_curl_request_method(Request $request, array $options): array
    {
        if ($request->method() === Request::POST) {
            $options[CURLOPT_POST] = TRUE;
        } else {
            $options[CURLOPT_CUSTOMREQUEST] = $request->method();
        }
        return $options;
    }

}
