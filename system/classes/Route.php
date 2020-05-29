<?php
/**
 * Routes are used to determine the controller and action for a requested URI.
 * Every route generates a regular expression which is used to match a URI
 * and a route. Routes may also contain keys which can be used to set the
 * controller, action, and parameters.
 *
 * Each <key> will be translated to a regular expression using a default
 * regular expression pattern. You can override the default pattern by providing
 * a pattern for the key.
 *
 * Routes also provide a way to generate URIs (called "reverse routing"), which
 * makes them an extremely powerful and flexible way to generate internal links.
 *
 * @package    Modseven
 * @category   Driver
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven;

use Exception;

class Route
{
    // Matches a URI group and captures the contents
    public const REGEX_GROUP = '\(((?:(?>[^()]+)|(?R))*)\)';

    // Defines the pattern of a <segment>
    public const REGEX_KEY = '<([a-zA-Z0-9_]++)>';

    // What can be part of a <segment> value
    public const REGEX_SEGMENT = '[^/.,;?\n]++';

    // What must be escaped in the route regex
    public const REGEX_ESCAPE = '[.\\+*?[^\\]${}=!|]';

    /**
     * default protocol for all routes
     * @var string
     */
    public static string $default_protocol = 'http://';

    /**
     * list of valid localhost entries
     * @var array
     */
    public static array $localhosts = [false, '', 'local', 'localhost'];

    /**
     * default action for all routes
     * @var string
     */
    public static string $default_action = 'index';

    /**
     * Indicates whether routes are cached
     * @var bool
     */
    public static bool $cache = false;

    /**
     * Routes
     * @var  array
     */
    protected static array $_routes = [];

    /**
     * route filters
     * @var  array
     */
    protected array $_filters = [];

    /**
     * route URI
     * @var string
     */
    protected ?string $_uri = '';

    /**
     * @var array
     */
    protected ?array $_regex = [];

    /**
     * @var  array
     */
    protected array $_defaults = ['action' => 'index', 'host' => false];

    /**
     * @var null|string
     */
    protected ?string $_route_regex = null;

    /**
     * Creates a new route. Sets the URI and regular expressions for keys.
     * Routes should always be created with [Route::set] or they will not
     * be properly stored.
     *
     * The $uri parameter should be a string for basic regex matching.
     *
     * @param string $uri route URI pattern
     * @param array $regex key patterns
     */
    public function __construct(?string $uri = NULL, ?array $regex = NULL)
    {
        if ($uri === NULL) {
            // Assume the route is from cache
            return;
        }

        if (!empty($uri)) {
            $this->_uri = $uri;
        }

        if (!empty($regex)) {
            $this->_regex = $regex;
        }

        // Store the compiled regex locally
        $this->_route_regex = self::compile($uri, $regex);
    }

    /**
     * Returns the compiled regular expression for the route. This translates
     * keys and optional groups to a proper PCRE regular expression.
     *
     * @param string     $uri   Url to compile
     * @param null|array $regex Regex to use
     *
     * @return  string
     */
    public static function compile(string $uri, ?array $regex = NULL): string
    {
        // The URI should be considered literal except for keys and optional parts
        // Escape everything preg_quote would escape except for : ( ) < >
        $expression = preg_replace('#' . static::REGEX_ESCAPE . '#', '\\\\$0', $uri);

        if (strpos($expression, '(') !== FALSE) {
            // Make optional parts of the URI non-capturing and optional
            $expression = str_replace(['(', ')'], ['(?:', ')?'], $expression);
        }

        // Insert default regex for keys
        $expression = str_replace(['<', '>'], ['(?P<', '>' . static::REGEX_SEGMENT . ')'], $expression);

        if ($regex) {
            $search = $replace = [];
            foreach ($regex as $key => $value) {
                $search[] = "<$key>" . static::REGEX_SEGMENT;
                $replace[] = "<$key>$value";
            }

            // Replace the default regex with the user-specified regex
            $expression = str_replace($search, $replace, $expression);
        }

        return '#^' . $expression . '$#uD';
    }

    /**
     * Stores a named route and returns it. The "action" will always be set to
     * "index" if it is not defined.
     *
     * @param string $name route name
     * @param string $uri URI pattern
     * @param array $regex regex patterns for route keys
     * @return  Route
     */
    public static function set(string $name, ?string $uri = NULL, ?array $regex = NULL): Route
    {
        return static::$_routes[$name] = new self($uri, $regex);
    }

    /**
     * Retrieves all named routes.
     *
     * @return  array  routes by name
     */
    public static function all(): array
    {
        return static::$_routes;
    }

    /**
     * Get the name of a route.
     *
     * @param Route $route instance
     * @return  string
     */
    public static function name(Route $route): string
    {
        return array_search($route, static::$_routes, true);
    }

    /**
     * Saves or loads the route cache. If your routes will remain the same for
     * a long period of time, use this to reload the routes from the cache
     * rather than redefining them on every page load.
     *
     * @param boolean $save cache the current routes
     * @param boolean $append append, rather than replace, cached routes when loading
     *
     * @return  void|boolean    when saving routes\when loading routes
     *
     * @throws \Modseven\Exception
     */
    public static function cache($save = FALSE, $append = FALSE)
    {
        if ($save === TRUE) {
            try {
                // Cache all defined routes
                Core::cache('\Modseven\Route::cache()', static::$_routes);
            } catch (Exception $e) {
                // We most likely have a lambda in a route, which cannot be cached
                throw new \Modseven\Exception('One or more routes could not be cached (:message)', [
                    ':message' => $e->getMessage(),
                ], 0, $e);
            }
        } else {
            if ($routes = Core::cache('\Modseven\Route::cache()')) {
                if ($append) {
                    // Append cached routes
                    static::$_routes += $routes;
                } else {
                    // Replace existing routes
                    static::$_routes = $routes;
                }

                // Routes were cached
                return static::$cache = TRUE;
            }

            // Routes were not cached
            return static::$cache = FALSE;
        }
    }

    /**
     * Create a URL from a route name.
     *
     * @param string $name route name
     * @param array $params URI parameters
     * @param mixed $protocol protocol string or boolean, adds protocol and domain
     *
     * @return  string
     *
     * @throws \Modseven\Exception
     */
    public static function url(string $name, ?array $params = NULL, $protocol = NULL): string
    {
        $route = self::get($name);

        // Create a URI with the route and convert it to a URL
        if ($route->isExternal()) {
            return $route->uri($params);
        }
        return URL::site($route->uri($params), $protocol);
    }

    /**
     * Retrieves a named route.
     *
     *     $route = Route::get('default');
     *
     * @param string $name route name
     * @return  Route
     * @throws  \Modseven\Exception
     */
    public static function get(string $name): Route
    {
        if (!isset(static::$_routes[$name])) {
            throw new \Modseven\Exception('The requested route does not exist: :route',
                [':route' => $name]);
        }

        return static::$_routes[$name];
    }

    /**
     * Returns whether this route is an external route
     * to a remote controller.
     *
     * @return  boolean
     */
    public function isExternal(): bool
    {
        return !in_array(Arr::get($this->_defaults, 'host', false), static::$localhosts, true);
    }

    /**
     * Generates a URI for the current route based on the parameters given.
     *
     * @param array $params URI parameters
     *
     * @return  string
     */
    public function uri(?array $params = NULL): string
    {
        if ($params) {
            // @issue #4079 rawurlencode parameters
            $params = array_map('rawurlencode', $params);
            // decode slashes back, see Apache docs about AllowEncodedSlashes and AcceptPathInfo
            $params = str_replace(['%2F', '%5C'], ['/', '\\'], $params);
        }

        $defaults = $this->_defaults;

        /**
         * Recursively compiles a portion of a URI specification by replacing
         * the specified parameters and any optional parameters that are needed.
         *
         * @param string $portion Part of the URI specification
         * @param boolean $required Whether or not parameters are required (initially)
         * @return  array   Tuple of the compiled portion and whether or not it contained specified parameters
         */
        $compile = static function ($portion, $required) use (&$compile, $defaults, $params) {
            $missing = [];

            $pattern = '#(?:' . Route::REGEX_KEY . '|' . Route::REGEX_GROUP . ')#';
            $result = preg_replace_callback($pattern, static function ($matches) use (&$compile, $defaults, &$missing, $params, &$required) {
                if ($matches[0][0] === '<') {
                    // Parameter, unwrapped
                    $param = $matches[1];

                    if (isset($params[$param])) {
                        // This portion is required when a specified
                        // parameter does not match the default
                        $required = ($required || !isset($defaults[$param]) || $params[$param] !== $defaults[$param]);

                        // Add specified parameter to this result
                        return $params[$param];
                    }

                    // Add default parameter to this result
                    if (isset($defaults[$param])) {
                        return $defaults[$param];
                    }

                    // This portion is missing a parameter
                    $missing[] = $param;
                } else {
                    // Group, unwrapped
                    $result = $compile($matches[2], FALSE);

                    if ($result[1]) {
                        // This portion is required when it contains a group
                        // that is required
                        $required = TRUE;

                        // Add required groups to this result
                        return $result[0];
                    }

                    // Do not add optional groups to this result
                }
                return null;
            }, $portion);

            if ($required && $missing) {
                throw new \Modseven\Exception(
                    'Required route parameter not passed: :param',
                    [':param' => reset($missing)]
                );
            }

            return [$result, $required];
        };

        [$uri] = $compile($this->_uri, true);

        // Trim all extra slashes from the URI
        $uri = preg_replace('#//+#', '/', rtrim($uri, '/'));

        if ($this->isExternal()) {
            // Need to add the host to the URI
            $host = $this->_defaults['host'];

            if (strpos($host, '://') === FALSE) {
                // Use the default defined protocol
                $host = static::$default_protocol . $host;
            }

            // Clean up the host and prepend it to the URI
            $uri = rtrim($host, '/') . '/' . $uri;
        }

        return $uri;
    }

    /**
     * Provides default values for keys when they are not present. The default
     * action will always be "index" unless it is overloaded here.
     *
     * If no parameter is passed, this method will act as a getter.
     *
     * @param array $defaults key values
     * @return  self|array
     */
    public function defaults(?array $defaults = NULL)
    {
        if ($defaults === NULL) {
            return $this->_defaults;
        }

        $this->_defaults = $defaults;

        return $this;
    }

    /**
     * Filters to be run before route parameters are returned:
     *
     * To prevent a route from matching, return `FALSE`. To replace the route
     * parameters, return an array.
     *
     * [!!] Default parameters are added before filters are called!
     *
     * @param string|array $callback
     * @return  self
     * @throws  \Modseven\Exception
     */
    public function filter($callback): self
    {
        if (!is_callable($callback)) {
            throw new \Modseven\Exception('Invalid Route::callback specified');
        }

        $this->_filters[] = $callback;

        return $this;
    }

    /**
     * Tests if the route matches a given Request. A successful match will return
     * all of the routed parameters as an array. A failed match will return
     * boolean FALSE.
     *
     * @param Request $request Request object to match
     * @return  array|false       success|failure
     */
    public function matches(Request $request)
    {
        // Get the URI from the Request
        $uri = trim($request->uri(), '/');

        if (!preg_match($this->_route_regex, $uri, $matches)) {
            return false;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_int($key)) {
                // Skip all unnamed keys
                continue;
            }

            // Set the value for all matched keys
            $params[$key] = $value;
        }

        foreach ($this->_defaults as $key => $value) {
            if (!isset($params[$key]) || $params[$key] === '') {
                // Set default values for any key that was not matched
                $params[$key] = $value;
            }
        }
        
        if ($this->_filters) {
            foreach ($this->_filters as $callback) {
                // Execute the filter giving it the route, params, and request
                $return = $callback($this, $params, $request);

                if ($return === FALSE) {
                    // Filter has aborted the match
                    return FALSE;
                }

                // Filter has modified the parameters
                $params = $return;
            }
        }

        return $params;
    }

}
