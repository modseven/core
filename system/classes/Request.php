<?php
/**
 * Request. Uses the [Route] class to determine what
 * [Controller] to send the request to.
 *
 * @package    Modseven
 * @category   Base
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

use Modseven\HTTP\Exception;

class Request implements HTTP\Request
{
    /**
     * client user agent
     * @var string
     */
    public static string $user_agent = '';

    /**
     * client IP address
     * @var string
     */
    public static string $client_ip = '0.0.0.0';

    /**
     * trusted proxy server IPs
     * @var array
     */
    public static array $trusted_proxies = ['127.0.0.1', 'localhost', 'localhost.localdomain'];

    /**
     * main request instance
     * @var null|Request
     */
    public static ?Request $initial = null;

    /**
     * currently executing request instance
     * @var null|Request
     */
    public static ?Request $current = null;

    /**
     * the x-requested-with header which most likely will be xmlhttprequest
     * @var  string
     */
    protected string $_requested_with;

    /**
     * method: GET, POST, PUT, DELETE, HEAD, etc
     * @var string
     */
    protected string $_method = 'GET';

    /**
     * protocol: HTTP/1.1, FTP, CLI, etc
     * @var null|string
     */
    protected ?string $_protocol = null;

    /**
     * @var boolean
     */
    protected bool $_secure = false;

    /**
     * referring URL
     * @var string
     */
    protected string $_referrer;

    /**
     * route matched for this request
     * @var null|Route
     */
    protected ?Route $_route = null;

    /**
     * array of routes to manually look at instead of the global namespace
     * @var null|array
     */
    protected ?array $_routes = null;

    /**
     * headers to sent as part of the request
     * @var null|HTTP\Header
     */
    protected ?HTTP\Header $_header = null;

    /**
     * the body
     * @var string
     */
    protected string $_body = '';

    /**
     * controller directory
     * @var string
     */
    protected string $_directory = '';

    /**
     * Namespace
     * @var string
     */
    protected string $_namespace;

    /**
     * controller to be executed
     * @var string
     */
    protected string $_controller;

    /**
     * action to be executed in the controller
     * @var string
     */
    protected string $_action;

    /**
     * The URI of the request
     * @var string
     */
    protected ?string $_uri = null;

    /**
     * external request
     * @var boolean
     */
    protected bool $_external = false;

    /**
     * parameters from the route
     * @var  array
     */
    protected array $_params = [];

    /**
     * query parameters
     * @var array
     */
    protected array $_get = [];

    /**
     * post parameters
     * @var array
     */
    protected array $_post = [];

    /**
     * cookies to send with the request
     * @var array
     */
    protected array $_cookies = [];

    /**
     * @var null|Request\Client
     */
    protected ?Request\Client $_client = null;

    /**
     * Creates a new request object for the given URI. New requests should be
     * Created using the [Request::factory] method.
     *
     * If $cache parameter is set, the response for the request will attempt to
     * be retrieved from the cache.
     *
     * @param string $uri URI of the request
     * @param array $client_params Array of params to pass to the request client
     * @param bool $allow_external Allow external requests? (deprecated in 3.3)
     * @param array $injected_routes An array of routes to use, for testing
     *
     * @throws  Request\Exception
     */
    public function __construct(string $uri, array $client_params = [], bool $allow_external = TRUE, array $injected_routes = [])
    {
        // Initialise the header
        $this->_header = new HTTP\Header([]);

        // Assign injected routes
        $this->_routes = $injected_routes;

        // Cleanse query parameters from URI (faster that parse_url())
        $split_uri = explode('?', $uri);
        $uri = array_shift($split_uri);

        if ($split_uri) {
            parse_str($split_uri[0], $this->_get);
        }

        // Detect protocol (if present)
        // $allow_external = FALSE prevents the default index.php from
        // being able to proxy external pages.
        if (!$allow_external || (strpos($uri, '://') === FALSE && strncmp($uri, '//', 2))) {
            // Remove leading and trailing slashes from the URI
            $this->_uri = trim($uri, '/');

            // Apply the client
            $this->_client = new Request\Client\Internal($client_params);
        } else {
            // Create a route
            $this->_route = new Route($uri);

            // Store the URI
            $this->_uri = $uri;

            // Set the security setting if required
            if (strpos($uri, 'https://') === 0) {
                $this->secure(TRUE);
            }

            // Set external state
            $this->_external = TRUE;

            // Setup the client
            $this->_client = Request\Client\External::factory($client_params);
        }
    }

    /**
     * Creates a new request object for the given URI. New requests should be
     * Created using the [Request::factory] method.
     *
     * If $cache parameter is set, the response for the request will attempt to
     * be retrieved from the cache.
     *
     * @param string|bool $uri URI of the request
     * @param array $client_params An array of params to pass to the request client
     * @param bool $allow_external Allow external requests? (deprecated in 3.3)
     * @param array $injected_routes An array of routes to use, for testing
     *
     * @return  void|Request
     *
     * @throws Exception
     * @throws Request\Exception
     * @throws \Exception
     */
    public static function factory($uri = TRUE, array $client_params = [], bool $allow_external = TRUE, array $injected_routes = [])
    {
        // If this is the initial request
        if (!static::$initial) {
            $protocol = HTTP::$protocol;

            $method = $_SERVER['REQUEST_METHOD'] ?? HTTP\Request::GET;

            if ((
                    !empty($_SERVER['HTTPS'])
                    && filter_var($_SERVER['HTTPS'], FILTER_VALIDATE_BOOLEAN))
                || ((isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
                    && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                && in_array($_SERVER['REMOTE_ADDR'], static::$trusted_proxies, true))) {
                // This request is secure
                $secure = TRUE;
            }

            if (isset($_SERVER['HTTP_REFERER'])) {
                // There is a referrer for this request
                $referrer = $_SERVER['HTTP_REFERER'];
            }

            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                // Browser type
                static::$user_agent = $_SERVER['HTTP_USER_AGENT'];
            }

            if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                // Typically used to denote AJAX requests
                $requested_with = $_SERVER['HTTP_X_REQUESTED_WITH'];
            }

            if (isset($_SERVER['HTTP_CF_CONNECTING_IP'], $_SERVER['REMOTE_ADDR']) &&
                in_array($_SERVER['REMOTE_ADDR'], static::$trusted_proxies, true)) {

                // If using CloudFlare, client IP address is sent with this header
                static::$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
            } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['REMOTE_ADDR']) &&
                      in_array($_SERVER['REMOTE_ADDR'], static::$trusted_proxies, true)) {
                // Use the forwarded IP address, typically set when the
                // client is using a proxy server.
                // Format: "X-Forwarded-For: client1, proxy1, proxy2"
                $client_ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

                static::$client_ip = array_shift($client_ips);

                unset($client_ips);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['REMOTE_ADDR']) &&
                      in_array($_SERVER['REMOTE_ADDR'], static::$trusted_proxies, true)) {
                // Use the forwarded IP address, typically set when the
                // client is using a proxy server.
                $client_ips = explode(',', $_SERVER['HTTP_CLIENT_IP']);

                static::$client_ip = trim(end($client_ips));

                unset($client_ips);
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                // The remote IP address
                static::$client_ip = $_SERVER['REMOTE_ADDR'];
            }

            if ($method !== HTTP\Request::GET) {
                // Ensure the raw body is saved for future use
                $body = file_get_contents('php://input');
            }

            if ($uri === TRUE) {
                // Attempt to guess the proper URI
                $uri = self::detect_uri();
            }

            $cookies = [];

            if ($cookie_keys = array_keys($_COOKIE)) {
                foreach ($cookie_keys as $key) {
                    $cookies[$key] = Cookie::get($key);
                }
            }

            // Create the instance singleton
            static::$initial = $request = new self($uri, $client_params, $allow_external, $injected_routes);

            // Store global GET and POST data in the initial request only
            $request->protocol($protocol)
                ->query($_GET)
                ->post($_POST);

            if (isset($secure)) {
                // Set the request security
                $request->secure($secure);
            }

            if (isset($method)) {
                // Set the request method
                $request->method($method);
            }

            if (isset($referrer)) {
                // Set the referrer
                $request->referrer($referrer);
            }

            if (isset($requested_with)) {
                // Apply the requested with variable
                $request->requested_with($requested_with);
            }

            if (isset($body)) {
                // Set the request body (probably a PUT type)
                $request->body($body);
            }

            if (isset($cookies)) {
                $request->cookie($cookies);
            }
        } else {
            $request = new self($uri, $client_params, $allow_external, $injected_routes);
        }

        return $request;
    }

    /**
     * Automatically detects the URI of the main request using PATH_INFO,
     * REQUEST_URI, PHP_SELF or REDIRECT_URL.
     *
     * @return  string  URI of the main request
     * @throws  Exception
     */
    public static function detect_uri(): string
    {
        if (!empty($_SERVER['PATH_INFO'])) {
            // PATH_INFO does not contain the docroot or index
            $uri = $_SERVER['PATH_INFO'];
        } else {
            // REQUEST_URI and PHP_SELF include the docroot and index

            if (isset($_SERVER['REQUEST_URI'])) {
                /**
                 * We use REQUEST_URI as the fallback value. The reason
                 * for this is we might have a malformed URL such as:
                 *
                 *  http://localhost/http://example.com/judge.php
                 *
                 * which parse_url can't handle. So rather than leave empty
                 * handed, we'll use this.
                 */
                $uri = $_SERVER['REQUEST_URI'];

                if ($request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) {
                    // Valid URL path found, set it.
                    $uri = $request_uri;
                }

                // Decode the request URI
                $uri = rawurldecode($uri);
            } elseif (isset($_SERVER['PHP_SELF'])) {
                $uri = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['REDIRECT_URL'])) {
                $uri = $_SERVER['REDIRECT_URL'];
            } else {
                // If you ever see this error, please report an issue.
                // along with any relevant information about your web server setup. Thanks!
                throw new Exception('Unable to detect the URI using PATH_INFO, REQUEST_URI, PHP_SELF or REDIRECT_URL');
            }

            // Get the path from the base URL, including the index file
            $base_url = parse_url(Core::$base_url, PHP_URL_PATH);

            if (strpos($uri, $base_url) === 0) {
                // Remove the base URL from the URI
                $uri = (string)substr($uri, strlen($base_url));
            }

            if (Core::$index_file && strpos($uri, Core::$index_file) === 0) {
                // Remove the index file from the URI
                $uri = (string)substr($uri, strlen(Core::$index_file));
            }
        }

        return $uri;
    }

    /**
     * Gets or sets the HTTP protocol. If there is no current protocol set,
     * it will use the default set in HTTP::$protocol
     *
     * @param string $protocol Protocol to set to the request
     * @return  mixed
     */
    public function protocol(?string $protocol = NULL)
    {
        if ($protocol === NULL) {
            if ($this->_protocol) {
                return $this->_protocol;
            }

            return $this->_protocol = HTTP::$protocol;
        }

        // Act as a setter
        $this->_protocol = strtoupper($protocol);
        return $this;
    }

    /**
     * Getter/Setter to the security settings for this request. This
     * method should be treated as immutable.
     *
     * @param boolean $secure is this request secure?
     * @return  mixed
     */
    public function secure(?bool $secure = NULL)
    {
        if ($secure === NULL) {
            return $this->_secure;
        }

        // Act as a setter
        $this->_secure = (bool)$secure;
        return $this;
    }

    /**
     * Gets or sets the HTTP method. Usually GET, POST, PUT or DELETE in
     * traditional CRUD applications.
     *
     * @param string $method Method to use for this request
     * @return  mixed
     */
    public function method(?string $method = NULL)
    {
        if ($method === NULL) {
            // Act as a getter
            return $this->_method;
        }

        // Act as a setter
        $this->_method = strtoupper($method);

        return $this;
    }

    /**
     * Sets and gets the referrer from the request.
     *
     * @param string $referrer
     * @return  mixed
     */
    public function referrer(?string $referrer = NULL)
    {
        if ($referrer === NULL) {
            // Act as a getter
            return $this->_referrer;
        }

        // Act as a setter
        $this->_referrer = (string)$referrer;

        return $this;
    }

    /**
     * Gets and sets the requested with property, which should
     * be relative to the x-requested-with pseudo header.
     *
     * @param string $requested_with Requested with value
     * @return  mixed
     */
    public function requested_with(?string $requested_with = NULL)
    {
        if ($requested_with === NULL) {
            // Act as a getter
            return $this->_requested_with;
        }

        // Act as a setter
        $this->_requested_with = strtolower($requested_with);

        return $this;
    }

    /**
     * Gets or sets the HTTP body of the request. The body is
     * included after the header, separated by a single empty new line.
     *
     * @param string $content Content to set to the object
     * @return  mixed
     */
    public function body(?string $content = NULL)
    {
        if ($content === NULL) {
            // Act as a getter
            return $this->_body;
        }

        // Act as a setter
        $this->_body = $content;

        return $this;
    }

    /**
     * Set and get cookies values for this request.
     *
     * @param mixed $key Cookie name, or array of cookie values
     * @param string $value Value to set to cookie
     * @return  mixed
     */
    public function cookie($key = NULL, string $value = NULL)
    {
        if (is_array($key)) {
            // Act as a setter, replace all cookies
            $this->_cookies = $key;
            return $this;
        }
        if ($key === NULL) {
            // Act as a getter, all cookies
            return $this->_cookies;
        }
        if ($value === NULL) {
            // Act as a getting, single cookie
            return $this->_cookies[$key] ?? null;
        }

        // Act as a setter for a single cookie
        $this->_cookies[$key] = $value;

        return $this;
    }

    /**
     * Return the currently executing request. This is changed to the current
     * request when [Request::execute] is called and restored when the request
     * is completed.
     *
     * @return  Request
     */
    public static function current(): Request
    {
        return static::$current;
    }

    /**
     * Returns the first request encountered by this framework. This will should
     * only be set once during the first [Request::factory] invocation.
     *
     * @return  Request
     */
    public static function initial(): Request
    {
        return static::$initial;
    }

    /**
     * Returns information about the initial user agent.
     *
     * @param mixed $value array or string to return: browser, version, robot, mobile, platform
     *
     * @return  mixed   requested information, FALSE if nothing is found
     *
     * @throws \Modseven\Exception
     */
    public static function user_agent($value)
    {
        return Text::user_agent(static::$user_agent, $value);
    }

    /**
     * Determines if a file larger than the post_max_size has been uploaded. PHP
     * does not handle this situation gracefully on its own, so this method
     * helps to solve that problem.
     *
     * @return  boolean
     *
     * @throws \Modseven\Exception
     */
    public static function post_max_size_exceeded(): bool
    {
        // Make sure the request method is POST
        if (static::$initial->method() !== HTTP\Request::POST) {
            return false;
        }

        // Get the post_max_size in bytes
        $max_bytes = Num::bytes(ini_get('post_max_size'));

        // Error occurred if method is POST, and content length is too long
        return (Arr::get($_SERVER, 'CONTENT_LENGTH') > $max_bytes);
    }

    /**
     * Parses an accept header and returns an array (type => quality) of the
     * accepted types, ordered by quality.
     *
     * @param string $header Header to parse
     * @param array $accepts Default values
     * @return  array
     */
    protected static function _parse_accept(string & $header, ?array $accepts = NULL): array
    {
        if (!empty($header)) {
            // Get all of the types
            foreach (explode(',', $header) as $type) {
                // Split the type into parts
                $parts = explode(';', $type);

                // Make the type only the MIME
                $type = trim(array_shift($parts));

                // Default quality is 1.0
                $quality = 1.0;

                foreach ($parts as $part) {
                    // Prevent undefined $value notice below
                    if (strpos($part, '=') === FALSE) {
                        continue;
                    }

                    // Separate the key and value
                    [$key, $value] = explode('=', trim($part));

                    if ($key === 'q') {
                        // There is a quality for this type
                        $quality = (float)trim($value);
                    }
                }

                // Add the accept type and quality
                $accepts[$type] = $quality;
            }
        }

        // Make sure that accepts is an array
        $accepts = (array)$accepts;

        // Order by quality
        arsort($accepts);

        return $accepts;
    }

    /**
     * Returns the response as the string representation of a request.
     *
     * @return  string
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Renders the HTTP_Interaction to a string, producing
     *
     *  - Protocol
     *  - Headers
     *  - Body
     *
     *  If there are variables set to the `Modseven_Request::$_post`
     *  they will override any values set to body.
     *
     * @return  string
     */
    public function render(): string
    {
        if (!$post = $this->post()) {
            $body = $this->body();
        } else {
            $body = http_build_query($post, NULL, '&');
            $this->body($body)
                ->headers('content-type', 'application/x-www-form-urlencoded; charset=' . Core::$charset);
        }

        // Set the content length
        $this->headers('content-length', (string)$this->content_length());

        // If Modseven expose, set the user-agent
        if (Core::$expose) {
            $this->headers('user-agent', Core::version());
        }

        // Prepare cookies
        if ($this->_cookies) {
            $cookie_string = [];

            // Parse each
            foreach ($this->_cookies as $key => $value) {
                $cookie_string[] = $key . '=' . $value;
            }

            // Create the cookie string
            $this->_header['cookie'] = implode('; ', $cookie_string);
        }

        $output = $this->method() . ' ' . $this->uri() . ' ' . $this->protocol() . "\r\n";
        $output .= $this->_header;
        $output .= $body;

        return $output;
    }

    /**
     * Gets or sets HTTP POST parameters to the request.
     *
     * @param mixed $key Key or key value pairs to set
     * @param string $value Value to set to a key
     * @return  mixed
     */
    public function post($key = NULL, ?string $value = NULL)
    {
        if (is_array($key)) {
            // Act as a setter, replace all fields
            $this->_post = $key;

            return $this;
        }

        if ($key === NULL) {
            // Act as a getter, all fields
            return $this->_post;
        }
        if ($value === NULL) {
            // Act as a getter, single field
            return Arr::path($this->_post, $key);
        }

        // Act as a setter, single field
        $this->_post[$key] = $value;

        return $this;
    }

    /**
     * Gets or sets HTTP headers oo the request. All headers
     * are included immediately after the HTTP protocol definition during
     * transmission. This method provides a simple array or key/value
     * interface to the headers.
     *
     * @param mixed $key Key or array of key/value pairs to set
     * @param string $value Value to set to the supplied key
     * @return  mixed
     */
    public function headers($key = NULL, string $value = NULL)
    {
        if ($key instanceof HTTP\Header) {
            // Act a setter, replace all headers
            $this->_header = $key;

            return $this;
        }

        if (is_array($key)) {
            // Act as a setter, replace all headers
            $this->_header->exchangeArray($key);

            return $this;
        }

        if ($this->_header->count() === 0 && $this->is_initial()) {
            // Lazy load the request headers
            $this->_header = HTTP::request_headers();
        }

        if ($key === NULL) {
            // Act as a getter, return all headers
            return $this->_header;
        }
        if ($value === NULL) {
            // Act as a getter, single header
            return $this->_header->offsetExists($key) ? $this->_header->offsetGet($key) : NULL;
        }

        // Act as a setter for a single header
        $this->_header[$key] = $value;

        return $this;
    }

    /**
     * Returns whether this request is the initial request Modseven received.
     * Can be used to test for sub requests.
     *
     * @return  boolean
     */
    public function is_initial(): bool
    {
        return ($this === static::$initial);
    }

    /**
     * Returns the length of the body for use with
     * content header
     *
     * @return  integer
     */
    public function content_length(): int
    {
        return strlen($this->body());
    }

    /**
     * Sets and gets the uri from the request.
     *
     * @param string $uri
     * @return  mixed
     */
    public function uri(?string $uri = NULL): string
    {
        if ($uri === NULL) {
            // Act as a getter
            return ($this->_uri === '') ? '/' : $this->_uri;
        }

        // Act as a setter
        $this->_uri = $uri;

        return $this;
    }

    /**
     * Create a URL string from the current request. This is a shortcut for:
     *
     * @param mixed $protocol protocol string or Request object
     *
     * @return  string
     *
     * @throws \Modseven\Exception
     */
    public function url($protocol = NULL): string
    {
        if ($this->is_external()) {
            // If it's an external request return the URI
            return $this->uri();
        }

        // Create a URI with the current route, convert to a URL and returns
        return URL::site($this->uri(), $protocol);
    }

    /**
     * Readonly access to the [Request::$_external] property.
     *
     * @return  boolean
     */
    public function is_external(): bool
    {
        return $this->_external;
    }

    /**
     * Retrieves a value from the route parameters.
     *
     * @param string $key Key of the value
     * @param mixed $default Default value if the key is not set
     * @return  mixed
     */
    public function param(?string $key = NULL, $default = NULL)
    {
        if ($key === NULL) {
            // Return the full array
            return $this->_params;
        }

        return $this->_params[$key] ?? $default;
    }

    /**
     * Sets and gets the route from the request.
     *
     * @param Route $route
     * @return  mixed
     */
    public function route(?Route $route = NULL)
    {
        if ($route === NULL) {
            // Act as a getter
            return $this->_route;
        }

        // Act as a setter
        $this->_route = $route;

        return $this;
    }

    /**
     * Sets and gets the controllers namespace
     *
     * @param string $namespace Namespace of Controller
     * @return $this|string
     */
    public function namesp(?string $namespace = null)
    {
        if ($namespace === null) {
            // Act as getter
            return $this->_namespace;
        }

        // Act as setter
        $this->_namespace = $namespace;

        return $this;
    }

    /**
     * Sets and gets the directory for the controller.
     *
     * @param string $directory Directory to execute the controller from
     * @return  mixed
     */
    public function directory(?string $directory = NULL)
    {
        if ($directory === NULL) {
            // Act as a getter
            return $this->_directory;
        }

        // Act as a setter
        $this->_directory = (string)$directory;

        return $this;
    }

    /**
     * Sets and gets the controller for the matched route.
     *
     * @param string $controller Controller to execute the action
     * @return  mixed
     */
    public function controller(?string $controller = NULL)
    {
        if ($controller === NULL) {
            // Act as a getter
            return $this->_controller;
        }

        // Act as a setter
        $this->_controller = (string)$controller;

        return $this;
    }

    /**
     * Sets and gets the action for the controller.
     *
     * @param string $action Action to execute the controller from
     * @return  mixed
     */
    public function action(?string $action = NULL)
    {
        if ($action === NULL) {
            // Act as a getter
            return $this->_action;
        }

        // Act as a setter
        $this->_action = (string)$action;

        return $this;
    }

    /**
     * Provides access to the [Request_Client].
     *
     * @param Request\Client|null $client
     *
     * @return  Request\Client|self
     */
    public function client(?Request\Client $client = NULL)
    {
        if ($client === NULL) {
            return $this->_client;
        }
        $this->_client = $client;
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
     * @return  Response
     *
     * @throws  Request\Exception
     * @throws \Modseven\Exception
     */
    public function execute(): Response
    {
        if (!$this->_external) {
            $processed = self::process($this, $this->_routes);

            if ($processed) {
                // Store the matching route
                $this->_route = $processed['route'];
                $params = $processed['params'];

                // Is this route external?
                $this->_external = $this->_route->is_external();

                if (isset($params['directory'])) {
                    // Controllers are in a sub-directory
                    $this->_directory = $params['directory'];
                }

                // Store the namespace
                $this->_namespace = $params['namespace'] ?? Core::$app_ns;

                // Store the controller
                $this->_controller = $params['controller'];

                // Store the action
                $this->_action = $params['action'] ?? Route::$default_action;

                // These are accessible as public vars and can be overloaded
                unset($params['controller'], $params['action'], $params['directory']);

                // Params cannot be changed once matched
                $this->_params = $params;
            }
        }

        if (!$this->_route instanceof Route) {
            return HTTP\Exception::factory(404, 'Unable to find a route to match the URI: :uri', [
                ':uri' => $this->_uri,
            ])->request($this)
                ->get_response();
        }

        if (!$this->_client instanceof Request\Client) {
            throw new Request\Exception('Unable to execute :uri without a Modseven_Request_Client', [
                ':uri' => $this->_uri,
            ]);
        }

        return $this->_client->execute($this);
    }

    /**
     * Process a request to find a matching route
     *
     * @param Request $request Request
     * @param array $routes Route
     * @return  array
     */
    public static function process(Request $request, ?array $routes = NULL): ?array
    {
        // Load routes
        $routes = empty($routes) ? Route::all() : $routes;
        $params = NULL;

        foreach ($routes as $route) {
            // Use external routes for reverse routing only
            if ($route->is_external()) {
                continue;
            }

            // We found something suitable
            if ($params = $route->matches($request)) {
                return [
                    'params' => $params,
                    'route' => $route,
                ];
            }
        }

        return NULL;
    }

    /**
     * Returns whether this is an ajax request (as used by JS frameworks)
     *
     * @return  boolean
     */
    public function is_ajax(): bool
    {
        return ($this->requested_with() === 'xmlhttprequest');
    }

    /**
     * Gets or sets HTTP query string.
     *
     * @param mixed $key Key or key value pairs to set
     * @param string $value Value to set to a key
     * @return  mixed
     */
    public function query($key = NULL, ?string $value = NULL)
    {
        if (is_array($key)) {
            // Act as a setter, replace all query strings
            $this->_get = $key;

            return $this;
        }

        if ($key === NULL) {
            // Act as a getter, all query strings
            return $this->_get;
        }
        if ($value === NULL) {
            // Act as a getter, single query string
            return Arr::path($this->_get, $key);
        }

        // Act as a setter, single query string
        $this->_get[$key] = $value;

        return $this;
    }

}
