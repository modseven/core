<?php
/**
 * [Request_Client_External] provides a wrapper for all external request
 * processing. This class should be extended by all drivers handling external
 * requests.
 *
 * Supported out of the box:
 *  - Streams (default)
 *  - Curl (default if loaded)
 *
 * @package        Modseven\Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license        https://koseven.ga/LICENSE
 *
 */

namespace Modseven\Request\Client;

use Modseven\Core;
use Modseven\Arr;
use Modseven\Profiler;
use Modseven\Request\Client;
use Modseven\Response;
use Modseven\Request;
use Modseven\Request\Exception;

abstract class External extends Client
{
    /**
     * Use:
     *  - Request_Client_Stream (default)
     *  - Request_Client_HTTP
     *  - Request_Client_Curl
     *
     * @var    string    Defines the external client to use by default
     */
    public static string $client;

    /**
     * Request options
     *
     * @var     array
     * @link    http://www.php.net/manual/function.curl-setopt
     * @link    http://www.php.net/manual/http.request.options
     */
    protected array $_options = [];

    /**
     * Factory method to create a new Request_Client_External object based on
     * the client name passed, or defaulting to Request_Client_External::$client
     * by default.
     *
     * Request_Client_External::$client can be set in the application bootstrap.
     *
     * @param array $options Request options to pass to the client
     * @param string $client External client to use (to override default one)
     *
     * @return  External
     * @throws  Exception
     *
     */
    public static function factory(array $options = [], ?string $client = NULL): External
    {
        // If no client given determine which one to use (prefer the faster and mature ones)
        if ($client === NULL) {
            if (static::$client === NULL) {
                if (extension_loaded('curl')) {
                    static::$client = '\Modseven\Request\Client\Curl';
                } else {
                    static::$client = '\Modseven\Request\Client\Stream';
                }
            }

            $client = static::$client;
        }

        $client = new $client($options);

        // Check if client extends Request_Client_External
        if (!$client instanceof External) {
            throw new Exception(':client is not a valid external Request Client.', [
                'client' => get_class($client)
            ]);
        }

        // Set Request Options
        $client->options($options);

        return $client;
    }

    /**
     * Set and get options for this request.
     *
     * @param mixed $key Option name, or array of options
     * @param mixed $value Option value
     *
     * @return  mixed
     */
    public function options($key = NULL, $value = NULL)
    {
        if ($key === NULL) {
            return $this->_options;
        }

        if (is_array($key)) {
            $this->_options = $key;
        } elseif ($value === NULL) {
            return Arr::get($this->_options, $key);
        } else {
            $this->_options[$key] = $value;
        }

        return $this;
    }

    /**
     * Processes the request, executing the controller action that handles this
     * request, determined by the [Route].
     *
     * 1. Before the controller action is called, the [Controller::before] method
     * will be called.
     * 2. Next the controller action will be called.
     * 3. After the controller action is called, the [Controller::after] method
     * will be called.
     *
     * By default, the output from the controller is captured and returned, and
     * no headers are sent.
     *
     *     $request->execute();
     *
     * @param Request $request A request object
     * @param Response $response A response object
     *
     * @return Response
     * @throws Exception
     */
    public function executeRequest(Request $request, Response $response): Response
    {
        //@codeCoverageIgnoreStart
        if (Core::$profiling) {
            // Set the benchmark name
            $benchmark = '"' . $request->uri() . '"';

            if ($request !== Request::$initial && Request::$current) {
                // Add the parent request uri
                $benchmark .= ' « "' . Request::$current->uri() . '"';
            }

            // Start benchmarking
            $benchmark = Profiler::start('Requests', $benchmark);
        }
        //@codeCoverageIgnoreEnd

        // Store the current active request and replace current with new request
        $previous = Request::$current;
        Request::$current = $request;

        // Resolve the POST fields
        if ($post = $request->post()) {
            $request
                ->body(http_build_query($post, NULL, '&'))
                ->headers('content-type', 'application/x-www-form-urlencoded; charset=' . Core::$charset);
        }

        $request->headers('content-length', (string)$request->contentLength());

        // If Modseven expose, set the user-agent
        if (Core::$expose) {
            $request->headers('user-agent', Core::version());
        }

        try {
            $response = $this->_sendMessage($request, $response);
        } catch (\Exception $e) {
            // Restore the previous request
            Request::$current = $previous;

            //@codeCoverageIgnoreStart
            if (isset($benchmark)) {
                // Delete the benchmark, it is invalid
                Profiler::delete($benchmark);
            }
            //@codeCoverageIgnoreEnd

            // Re-throw the exception
            throw new Exception($e->getMessage(), null, $e->getCode(), $e);
        }

        // Restore the previous request
        Request::$current = $previous;

        //@codeCoverageIgnoreStart
        if (isset($benchmark)) {
            // Stop the benchmark
            Profiler::stop($benchmark);
        }
        //@codeCoverageIgnoreEnd

        // Return the response
        return $response;
    }

    /**
     * Sends the HTTP message [Request] to a remote server and processes
     * the response. This one needs to be implemented by all Request Drivers.
     *
     * @param Request $request Request to send
     * @param Response $response Response to send
     *
     * @return  Response
     */
    abstract protected function _sendMessage(Request $request, Response $response): Response;

}