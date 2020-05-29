<?php
/**
 * HTTP Caching adaptor class that provides caching services to the
 * Request Client class, using HTTP cache control logic as defined in
 * RFC 2616.
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Cache;

use Modseven\Arr;
use Modseven\Cache;
use Modseven\Request;
use Modseven\Request\Client;
use Modseven\Response;
use Modseven\HTTP\Header;
use Modseven\HTTP\Request as HTTP_Request;

class HTTP
{
    /**
     * Status keys
     * @var string
     */
    public const CACHE_STATUS_KEY = 'x-cache-status';
    public const CACHE_STATUS_SAVED = 'SAVED';
    public const CACHE_STATUS_HIT = 'HIT';
    public const CACHE_STATUS_MISS = 'MISS';
    public const CACHE_HIT_KEY = 'x-cache-hits';

    /**
     * Cache driver to use for HTTP caching
     * @var Cache
     */
    protected Cache $_cache;

    /**
     * Cache key generator callback
     * @var callable
     */
    protected $_cache_key_callback;

    /**
     * Defines whether this client should cache `private` cache directives
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.9
     *
     * @var bool
     */
    protected bool $_allow_private_cache = FALSE;

    /**
     * The timestamp of the request
     * @var int
     */
    protected int $_request_time;

    /**
     * The timestamp of the response
     * @var int
     */
    protected int $_response_time;

    /**
     * Constructor method for this class. Allows dependency injection of the
     * required components such as `Cache` and the cache key generator.
     *
     * @param array $options Caching Options
     *
     * @throws  Exception
     */
    public function __construct(array $options = [])
    {
        foreach ($options as $key => $value)
        {
            if (method_exists($this, $key))
            {
                $this->$key($value);
            }
        }

        if ($this->_cache_key_callback === null)
        {
            $this->cacheKeyCallback('\Modseven\Cache\HTTP::basicCacheKeyGenerator');
        }
    }

    /**
     * Sets or gets the cache key generator callback for this caching
     * class. The cache key generator provides a unique hash based on the
     * `Request` object passed to it.
     *
     * The default generator is \Modseven\Cache\HTTP::basic_cache_key_generator(), which
     * serializes the entire HTTP Request into a unique sha1 hash. This will
     * provide basic caching for static and simple dynamic pages. More complex
     * algorithms can be defined and then passed into this class using this method.
     *
     * @param callback $callback
     *
     * @throws  Exception
     *
     * @return  mixed
     */
    public function cachekeyCallback($callback = null)
    {
        if ($callback === null)
        {
            return $this->_cache_key_callback;
        }

        if (!is_callable($callback))
        {
            throw new Exception('cache_key_callback must be callable!');
        }

        $this->_cache_key_callback = $callback;
        return $this;
    }

    /**
     * Factory method for this class that provides a convenient dependency
     * injector for the Cache library.
     *
     * @param mixed $cache   Cache engine to use
     * @param array $options Options to set to this class
     *
     * @throws Exception
     *
     * @return self
     */
    public static function factory($cache, array $options = []): self
    {
        if (!$cache instanceof Cache)
        {
            $cache = Cache::instance($cache);
        }

        $options['cache'] = $cache;

        return new self($options);
    }

    /**
     * Executes the supplied Request with the supplied Request Client.
     * Before execution, the HTTP Cache adapter checks the request type,
     * destructive requests such as `POST`, `PUT` and `DELETE` will bypass
     * cache completely and ensure the response is not cached. All other
     * Request methods will allow caching, if the rules are met.
     *
     * @param Client   $client    Client to execute with Cache-Control
     * @param Request  $request   Request to execute with client
     * @param Response $response  Response to execute with client
     *
     * @throws Exception
     *
     * @return Response
     */
    public function execute(Client $client, Request $request, Response $response) : Response
    {
        if (!$this->_cache instanceof Cache)
        {
            return $client->executeRequest($request, $response);
        }

        // If this is a destructive request, by-pass cache completely
        if (in_array($request->method(), [
            HTTP_Request::POST,
            HTTP_Request::PUT,
            HTTP_Request::DELETE
        ], true))
        {
            // Kill existing caches for this request
            $this->invalidateCache($request);

            $response = $client->executeRequest($request, $response);

            $cache_control = Header::createCacheControl([
                'no-cache',
                'must-revalidate'
            ]);

            // Ensure client respects destructive action
            return $response->headers('cache-control', $cache_control);
        }

        // Create the cache key
        $cache_key = $this->createCacheKey($request, $this->_cache_key_callback);

        // Try and return cached version
        if (($cached_response = $this->cacheResponse($cache_key, $request)) instanceof Response)
        {
            return $cached_response;
        }

        // Start request time
        $this->_request_time = time();

        // Execute the request with the Request client
        $response = $client->executeRequest($request, $response);

        // Stop response time
        $this->_response_time = (time() - $this->_request_time);

        // Cache the response
        $this->cacheResponse($cache_key, $request, $response);

        $response->headers(static::CACHE_STATUS_KEY, static::CACHE_STATUS_MISS);

        return $response;
    }

    /**
     * Invalidate a cached response for the Request supplied.
     * This has the effect of deleting the response from the
     * Cache entry.
     *
     * @param Request $request Response to remove from cache
     */
    public function invalidateCache(Request $request): void
    {
        if (($cache = $this->cache()) instanceof Cache)
        {
            $cache->delete($this->createCacheKey($request, $this->_cache_key_callback));
        }
    }

    /**
     * Getter and setter for the internal caching engine,
     * used to cache responses if available and valid.
     *
     * @param Cache|null $cache
     *
     * @return self|Cache
     */
    public function cache(?Cache $cache = null)
    {
        if ($cache === null)
        {
            return $this->_cache;
        }

        $this->_cache = $cache;
        return $this;
    }

    /**
     * Creates a cache key for the request to use for caching
     * Response returned by Request::execute.
     *
     * This is the default cache key generating logic, but can be overridden
     * by setting \Modseven\Cache\HTTP::cache_key_callback()
     *
     * @param Request  $request  Request to create key for
     * @param callback $callback Optional callback to use instead of built-in method
     *
     * @return string
     */
    public function createCacheKey(Request $request, $callback = null): string
    {
        if (is_callable($callback))
        {
            return $callback($request);
        }
        return self::basicCacheKeyGenerator($request);
    }

    /**
     * Basic cache key generator that hashes the entire request and returns
     * it. This is fine for static content, or dynamic content where user
     * specific information is encoded into the request.
     *
     * @param Request $request Request to hash
     *                         
     * @return string
     */
    public static function basicCacheKeyGenerator(Request $request): string
    {
        $uri = $request->uri();
        $query = $request->query();
        $headers = $request->headers()->getArrayCopy();
        $body = $request->body();

        return sha1($uri . '?' . http_build_query($query, null, '&') . '~' . implode('~', $headers) . '~' . $body);
    }

    /**
     * Caches a [Response] using the supplied [Cache]
     * and the key generated by [Request_Client::_create_cache_key].
     *
     * If not response is supplied, the cache will be checked for an existing
     * one that is available.
     *
     * @param string        $key        The cache key to use
     * @param Request       $request    The HTTP Request
     * @param Response|null $response   The HTTP Response
     *
     * @throws Exception
     *
     * @return bool|Response
     */
    public function cacheResponse(string $key, Request $request, Response $response = null)
    {
        if (!$this->_cache instanceof Cache)
        {
            return false;
        }

        // Check for Pragma: no-cache
        if ($pragma = $request->headers('pragma'))
        {
            if ($pragma === 'no-cache')
            {
                return false;
            }
            if (is_array($pragma) && in_array('no-cache', $pragma, true))
            {
                return false;
            }
        }

        // If there is no response, lookup an existing cached response
        if ($response === null)
        {
            if (($response = $this->_cache->get($key, null)) === null)
            {
                return false;
            }

            $hit_count = $this->_cache->get(static::CACHE_HIT_KEY . $key);
            $this->_cache->set(static::CACHE_HIT_KEY . $key, ++$hit_count);

            // Update the header to have correct HIT status and count
            $response
                ->headers(static::CACHE_STATUS_KEY, static::CACHE_STATUS_HIT)
                ->headers(static::CACHE_HIT_KEY, $hit_count);

            return $response;
        }

        if (($ttl = $this->cacheLifetime($response)) === false)
        {
            return false;
        }

        $response->headers(static::CACHE_STATUS_KEY, static::CACHE_STATUS_SAVED);

        // Set the hit count to zero
        $this->_cache->set(static::CACHE_HIT_KEY . $key, 0);

        return $this->_cache->set($key, $response, $ttl);
    }

    /**
     * Calculates the total Time To Live based on the specification
     * RFC 2616 cache lifetime rules.
     *
     * @param Response $response Response to evaluate
     *
     * @return int|bool TTL value or false if the response should not be cached
     */
    public function cacheLifetime(Response $response)
    {
        // Get out of here if this cannot be cached
        if (!$this->setCache($response))
        {
            return false;
        }

        // Calculate apparent age
        if ($date = $response->headers('date'))
        {
            $apparent_age = max(0, $this->_response_time - strtotime($date));
        }
        else
        {
            $apparent_age = max(0, $this->_response_time);
        }

        // Calculate corrected received age
        if ($age = $response->headers('age'))
        {
            $corrected_received_age = max($apparent_age, (int)$age);
        }
        else
        {
            $corrected_received_age = $apparent_age;
        }

        // Corrected initial age
        $corrected_initial_age = $corrected_received_age + $this->requestExecutionTime();

        // Resident time
        $resident_time = time() - $this->_response_time;

        // Current age
        $current_age = $corrected_initial_age + $resident_time;

        // Prepare the cache freshness lifetime
        $ttl = null;

        // Cache control overrides
        if ($cache_control = $response->headers('cache-control')) {
            // Parse the cache control header
            $cache_control = Header::parseCacheControl($cache_control);

            if (isset($cache_control['max-age'])) {
                $ttl = $cache_control['max-age'];
            }

            if ($this->_allow_private_cache && isset($cache_control['s-maxage'], $cache_control['private'])) {
                $ttl = $cache_control['s-maxage'];
            }

            if (isset($cache_control['max-stale']) && !isset($cache_control['must-revalidate'])) {
                $ttl = $current_age + $cache_control['max-stale'];
            }
        }

        // If we have a TTL at this point, return
        if ($ttl !== null)
        {
            return $ttl;
        }

        if ($expires = $response->headers('expires'))
        {
            return strtotime($expires) - $current_age;
        }

        return false;
    }

    /**
     * Controls whether the response can be cached. Uses HTTP
     * protocol to determine whether the response can be cached.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616.html RFC 2616
     *
     * @param Response $response The Response
     *
     * @return bool
     */
    public function setCache(Response $response): bool
    {
        $headers = $response->headers()->getArrayCopy();

        if ($cache_control = Arr::get($headers, 'cache-control'))
        {
            // Parse the cache control
            $cache_control = Header::parseCacheControl($cache_control);

            // If the no-cache or no-store directive is set, return
            if (array_intersect($cache_control, ['no-cache', 'no-store']))
            {
                return false;
            }

            // Check for private cache and get out of here if invalid
            if (!$this->_allow_private_cache && in_array('private', $cache_control, true))
            {
                if (!isset($cache_control['s-maxage']))
                {
                    return false;
                }

                // If there is a s-maxage directive we can use that
                $cache_control['max-age'] = $cache_control['s-maxage'];
            }

            // Check that max-age has been set and if it is valid for caching
            if (isset($cache_control['max-age']) && $cache_control['max-age'] < 1)
            {
                return false;
            }
        }

        // Can't cache things that have expired already
        return !(!isset($cache_control['max-age'])
            && ($expires = Arr::get($headers, 'expires'))
            && strtotime($expires) <= time());
    }

    /**
     * Returns the duration of the last request execution.
     *
     * @return int|bool Returns false if the request hasn't finished executing, or is yet to be run.
     */
    public function requestExecutionTime()
    {
        if ($this->_request_time === NULL || $this->_response_time === null)
        {
            return false;
        }

        return $this->_response_time - $this->_request_time;
    }

    /**
     * Gets or sets the [Client::allow_private_cache] setting.
     * If set to `TRUE`, the client will also cache cache-control directives
     * that have the `private` setting.
     *
     * @param bool|null $setting Allow caching of privately marked responses
     *
     * @return self|bool
     */
    public function allowPrivateCache(?bool $setting = null)
    {
        if ($setting === null)
        {
            return $this->_allow_private_cache;
        }

        $this->_allow_private_cache = (bool)$setting;
        return $this;
    }
}