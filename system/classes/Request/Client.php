<?php
/**
 * Request Client. Processes a [Request] and handles [HTTP_Caching] if
 * available. Will usually return a [Response] object as a result of the
 * request unless an unexpected error occurs.
 *
 * @package    Modseven
 * @category   Driver
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Request;

use Modseven\Arr;
use Modseven\Cache;
use Modseven\Request;
use Modseven\Response;

abstract class Client
{
    /**
     * Caching library for request caching
     * @var null|Cache
     */
    protected ?Cache $_cache = null;

    /**
     * Should redirects be followed?
     * @var bool
     */
    protected bool $_follow = false;

    /**
     * Headers to preserve when following a redirect
     * @var array
     */
    protected array $_follow_headers = ['authorization'];

    /**
     * Follow 302 redirect with original request method?
     * @var bool
     */
    protected bool $_strict_redirect = true;

    /**
     * Callbacks to use when response contains given headers
     * @var array
     */
    protected array $_header_callbacks = [
        'Location' => '\Modseven\Request\Client::onHeaderLocation'
    ];

    /**
     * Maximum number of requests that header callbacks can trigger before the request is aborted
     * @var int
     */
    protected int $_max_callback_depth = 5;

    /**
     * Tracks the callback depth of the currently executing request
     * @var int
     */
    protected int $_callback_depth = 1;

    /**
     * Arbitrary parameters that are shared with header callbacks through their Request_Client object
     * @var array
     */
    protected array $_callback_params = [];

    /**
     * Creates a new `Request_Client` object,
     * allows for dependency injection.
     *
     * @param array $params Params
     */
    public function __construct(array $params = [])
    {
        foreach ($params as $key => $value) {
            if (method_exists($this, $key)) {
                $this->$key($value);
            }
        }
    }

    /**
     * The default handler for following redirects, triggered by the presence of
     * a Location header in the response.
     * The client's follow property must be set TRUE and the HTTP response status
     * one of 201, 301, 302, 303 or 307 for the redirect to be followed.
     *
     * @param Request  $request
     * @param Response $response
     * @param Client   $client
     *
     * @return Request|null
     *
     * @throws Exception
     */
    public static function onHeaderLocation(Request $request, Response $response, Client $client): ?Request
    {
        // Do we need to follow a Location header ?
        if ($client->follow() && in_array($response->status(), [201, 301, 302, 303, 307], true)) {
            // Figure out which method to use for the follow request
            switch ($response->status()) {
                default:
                case 301:
                case 307:
                    $follow_method = $request->method();
                    break;
                case 201:
                case 303:
                    $follow_method = Request::GET;
                    break;
                case 302:
                    // Cater for sites with broken HTTP redirect implementations
                    if ($client->strictRedirect()) {
                        $follow_method = $request->method();
                    } else {
                        $follow_method = Request::GET;
                    }
                    break;
            }

            // Prepare the additional request, copying any follow_headers that were present on the original request
            $orig_headers = $request->headers()->getArrayCopy();
            $follow_header_keys = array_intersect(array_keys($orig_headers), $client->followHeaders());
            $follow_headers = Arr::extract($orig_headers, $follow_header_keys);

            try
            {
                $follow_request = Request::factory($response->headers('Location'))
                                         ->method($follow_method)
                                         ->headers($follow_headers);
            }
            catch (\Exception $e)
            {
                throw new Exception($e->getMessage(), null, $e->getCode(), $e);
            }


            if ($follow_method !== Request::GET) {
                $follow_request->body($request->body());
            }

            return $follow_request;
        }

        return NULL;
    }

    /**
     * Getter and setter for the strict redirects setting
     *
     * [!!] HTTP/1.1 specifies that a 302 redirect should be followed using the
     * original request method. However, the vast majority of clients and servers
     * get this wrong, with 302 widely used for 'POST - 302 redirect - GET' patterns.
     * By default, Modseven's client is fully compliant with the HTTP spec. Some
     * non-compliant third party sites may require that strict_redirect is set
     * FALSE to force the client to switch to GET following a 302 response.
     *
     * @param bool $strict_redirect Boolean indicating if 302 redirects should be followed with the original method
     * @return self|bool
     */
    public function strictRedirect(?bool $strict_redirect = NULL)
    {
        if ($strict_redirect === NULL) {
            return $this->_strict_redirect;
        }

        $this->_strict_redirect = $strict_redirect;

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
     * @param Request $request
     * @return  Response
     * @throws  \Modseven\Exception
     */
    public function execute(Request $request): Response
    {
        // Prevent too much recursion of header callback requests
        if ($this->callbackDepth() > $this->maxCallbackDepth()) {
            throw new Client\Recursion\Exception('Could not execute request to :uri - too many recursions after :depth requests',
                [
                    ':uri' => $request->uri(),
                    ':depth' => $this->callbackDepth() - 1,
                ]);
        }

        // Execute the request and pass the currently used protocol
        $orig_response = $response = Response::factory(['_protocol' => $request->protocol()]);

        if (($cache = $this->cache()) instanceof Cache) {
            return $cache->execute($this, $request, $response);
        }

        $response = $this->executeRequest($request, $response);

        // Execute response callbacks
        foreach ($this->headerCallbacks() as $header => $callback) {
            if ($response->headers($header)) {
                $cb_result = $callback($request, $response, $this);

                if ($cb_result instanceof Request) {
                    // If the callback returns a request, automatically assign client params
                    $this->assignClientProperties($cb_result->client());
                    $cb_result->client()->callbackDepth($this->callbackDepth() + 1);

                    // Execute the request
                    $response = $cb_result->execute();
                } elseif ($cb_result instanceof Response) {
                    // Assign the returned response
                    $response = $cb_result;
                }

                // If the callback has created a new response, do not process any further
                if ($response !== $orig_response) {
                    break;
                }
            }
        }

        return $response;
    }

    /**
     * Getter/Setter for the callback depth property, which is used to track
     * how many recursions have been executed within the current request execution.
     *
     * @param int $depth Current recursion depth
     * @return self|int
     */
    public function callbackDepth(?int $depth = NULL)
    {
        if ($depth === NULL) {
            return $this->_callback_depth;
        }

        $this->_callback_depth = $depth;

        return $this;
    }

    /**
     * Getter and setter for the maximum callback depth property.
     *
     * This protects the main execution from recursive callback execution (eg
     * following infinite redirects, conflicts between callbacks causing loops
     * etc). Requests will only be allowed to nest to the level set by this
     * param before execution is aborted with a Request_Client_Recursion_Exception.
     *
     * @param int $depth Maximum number of callback requests to execute before aborting
     * @return self|int
     */
    public function maxCallbackDepth(?int $depth = NULL)
    {
        if ($depth === NULL) {
            return $this->_max_callback_depth;
        }

        $this->_max_callback_depth = $depth;

        return $this;
    }

    /**
     * Getter and setter for the internal caching engine,
     * used to cache responses if available and valid.
     *
     * @param Cache $cache engine to use for caching
     * @return  Cache|self
     */
    public function cache(?Cache $cache = NULL)
    {
        if ($cache === NULL) {
            return $this->_cache;
        }

        $this->_cache = $cache;
        return $this;
    }

    /**
     * Processes the request passed to it and returns the response from
     * the URI resource identified.
     *
     * This method must be implemented by all clients.
     *
     * @param Request $request request to execute by client
     * @param Response $response
     * @return  Response
     */
    abstract public function executeRequest(Request $request, Response $response): Response;

    /**
     * Getter and setter for the header callbacks array.
     *
     * Accepts an array with HTTP response headers as keys and a PHP callback
     * function as values. These callbacks will be triggered if a response contains
     * the given header and can either issue a subsequent request or manipulate
     * the response as required.
     *
     * By default, the [Request_Client::on_header_location] callback is assigned
     * to the Location header to support automatic redirect following.
     *
     * @param array $header_callbacks Array of callbacks to trigger on presence of given headers
     * @return self|array
     */
    public function headerCallbacks(?array $header_callbacks = NULL)
    {
        if ($header_callbacks === NULL) {
            return $this->_header_callbacks;
        }

        $this->_header_callbacks = $header_callbacks;

        return $this;
    }

    /**
     * Assigns the properties of the current Request_Client to another
     * Request_Client instance - used when setting up a subsequent request.
     *
     * @param Client $client
     */
    public function assignClientProperties(Client $client): void
    {
        $client->cache($this->cache());
        $client->follow($this->follow());
        $client->followHeaders($this->followHeaders());
        $client->headerCallbacks($this->headerCallbacks());
        $client->maxCallbackDepth($this->maxCallbackDepth());
        $client->callbackParams($this->callbackParams());
    }

    /**
     * Getter and setter for the follow redirects
     * setting.
     *
     * @param bool $follow Boolean indicating if redirects should be followed
     * @return  bool|self
     */
    public function follow(?bool $follow = NULL)
    {
        if ($follow === NULL) {
            return $this->_follow;
        }

        $this->_follow = $follow;

        return $this;
    }

    /**
     * Getter and setter for the follow redirects
     * headers array.
     *
     * @param array $follow_headers Array of headers to be re-used when following a Location header
     * @return  array|self
     */
    public function followHeaders(?array $follow_headers = NULL)
    {
        if ($follow_headers === NULL) {
            return $this->_follow_headers;
        }

        $this->_follow_headers = array_map('strtolower', $follow_headers);

        return $this;
    }

    /**
     * Getter/Setter for the callback_params array, which allows additional
     * application-specific parameters to be shared with callbacks.
     *
     * @param string|array $param
     * @param mixed $value
     * @return self|mixed
     */
    public function callbackParams($param = NULL, $value = NULL)
    {
        // Getter for full array
        if ($param === NULL) {
            return $this->_callback_params;
        }

        // Setter for full array
        if (is_array($param)) {
            $this->_callback_params = $param;
            return $this;
        }
        // Getter for single value
        if ($value === NULL) {
            return Arr::get($this->_callback_params, $param);
        }

        // Setter for single value
        $this->_callback_params[$param] = $value;
        return $this;

    }

}
