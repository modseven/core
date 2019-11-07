<?php
/**
 * The Modseven_HTTP_Header class provides an Object-Orientated interface
 * to HTTP headers. This can parse header arrays returned from the
 * PHP functions `apache_request_headers()` or the `http_parse_headers()`
 * function available within the PECL HTTP library.
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

use ArrayObject;
use Modseven\Core;
use Modseven\Cookie;
use Modseven\Text;
use Modseven\Response;

class Header extends ArrayObject
{

    // Default Accept-* quality value if none supplied
    public const DEFAULT_QUALITY = 1;

    /**
     * Accept: (content) types
     * @var null|array
     */
    protected ?array $_accept_content = null;

    /**
     * Accept-Charset: parsed header
     * @var array
     */
    protected ?array $_accept_charset = null;

    /**
     * Accept-Encoding: parsed header
     * @var null|array
     */
    protected ?array $_accept_encoding = null;

    /**
     * Accept-Language: parsed header
     * @var null|array
     */
    protected ?array $_accept_language = null;

    /**
     * Accept-Language: language list of parsed header
     * @var null|array
     */
    protected ?array $_accept_language_list = null;

    /**
     * Constructor method for [Modseven_HTTP_Header]. Uses the standard constructor
     * of the parent `ArrayObject` class.
     *
     * @param array $input Input array
     * @param int $flags Flags
     * @param string $iterator_class The iterator class to use
     */
    public function __construct(array $input = [], int $flags = 0, string $iterator_class = 'ArrayIterator')
    {
        /**
         * @link http://www.w3.org/Protocols/rfc2616/rfc2616.html
         *
         * HTTP header declarations should be treated as case-insensitive
         */
        $input = array_change_key_case($input, CASE_LOWER);

        parent::__construct($input, $flags, $iterator_class);
    }

    /**
     * Generates a Cache-Control HTTP header based on the supplied array.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec13.html#sec13
     * @param array $cache_control Cache-Control to render to string
     * @return  string
     */
    public static function createCacheControl(array $cache_control): string
    {
        $parts = [];

        foreach ($cache_control as $key => $value) {
            $parts[] = is_int($key) ? $value : ($key . '=' . $value);
        }

        return implode(', ', $parts);
    }

    /**
     * Parses the Cache-Control header and returning an array representation of the Cache-Control
     * header.
     *
     * @param string $cache_control Cache Control headers
     * @return  mixed
     */
    public static function parseCacheControl(string $cache_control)
    {
        $directives = explode(',', strtolower($cache_control));

        if ($directives === FALSE) {
            return false;
        }

        $output = [];

        foreach ($directives as $directive) {
            if (strpos($directive, '=') !== FALSE) {
                [$key, $value] = explode('=', trim($directive), 2);

                $output[$key] = ctype_digit($value) ? (int)$value : $value;
            } else {
                $output[] = trim($directive);
            }
        }

        return $output;
    }

    /**
     * Returns the header object as a string, including
     * the terminating new line
     *
     * @return  string
     */
    public function __toString(): string
    {
        $header = '';

        foreach ($this as $key => $value) {
            // Put the keys back the Case-Convention expected
            $key = Text::ucfirst($key);

            if (is_array($value)) {
                $header .= $key . ': ' . implode(', ', $value) . "\r\n";
            } else {
                $header .= $key . ': ' . $value . "\r\n";
            }
        }

        return $header . "\r\n";
    }

    /**
     * Overloads the `ArrayObject::offsetUnset()` method to ensure keys
     * are lowercase.
     *
     * @param string $index
     * @return  void
     */
    public function offsetUnset($index): void
    {
        parent::offsetUnset(strtolower($index));
    }

    /**
     * Overloads the `ArrayObject::exchangeArray()` method to ensure that
     * all keys are changed to lowercase.
     *
     * @param mixed $input
     * @return  array
     */
    public function exchangeArray($input): array
    {
        /**
         * @link http://www.w3.org/Protocols/rfc2616/rfc2616.html
         *
         * HTTP header declarations should be treated as case-insensitive
         */
        $input = array_change_key_case((array)$input, CASE_LOWER);

        return parent::exchangeArray($input);
    }

    /**
     * Parses a HTTP Message header line and applies it to this HTTP_Header
     *
     * @param resource $resource the resource (required by Curl API)
     * @param string $header_line the line from the header to parse
     * @return  int
     */
    public function parseHeaderString($resource, string $header_line): int
    {
        if (preg_match_all('/(\w[^\s:]*):[ ]*([^\r\n]*(?:\r\n[ \t][^\r\n]*)*)/', $header_line, $matches)) {
            foreach ($matches[0] as $key => $value) {
                $this->offsetSet($matches[1][$key], $matches[2][$key], FALSE);
            }
        }

        return strlen($header_line);
    }

    /**
     * Overloads `ArrayObject::offsetSet()` to enable handling of header
     * with multiple instances of the same directive. If the `$replace` flag
     * is `FALSE`, the header will be appended rather than replacing the
     * original setting.
     *
     * @param mixed $index index to set `$newval` to
     * @param mixed $newval new value to set
     * @param boolean $replace replace existing value
     * @return  void
     */
    public function offsetSet($index, $newval, bool $replace = TRUE): void
    {
        // Ensure the index is lowercase
        $index = strtolower($index);

        if ($replace || !$this->offsetExists($index)) {
            parent::offsetSet($index, $newval);
        }

        $current_value = $this->offsetGet($index);

        if (is_array($current_value)) {
            $current_value[] = $newval;
        } else {
            $current_value = [$current_value, $newval];
        }

        parent::offsetSet($index, $current_value);
    }

    /**
     * Overloads the `ArrayObject::offsetExists()` method to ensure keys
     * are lowercase.
     *
     * @param string $index
     * @return  boolean
     */
    public function offsetExists($index): bool
    {
        return parent::offsetExists(strtolower($index));
    }

    /**
     * Overload the `ArrayObject::offsetGet()` method to ensure that all
     * keys passed to it are formatted correctly for this object.
     *
     * @param string $index index to retrieve
     * @return  mixed
     */
    public function offsetGet($index)
    {
        return parent::offsetGet(strtolower($index));
    }

    /**
     * Returns the preferred response content type based on the accept header
     * quality settings. If items have the same quality value, the first item
     * found in the array supplied as `$types` will be returned.
     *
     * @param array $types the content types to examine
     * @param boolean $explicit only allow explicit references, no wildcards
     *
     * @return  string  name of the preferred content type
     *
     * @throws Exception
     */
    public function preferredAccept(array $types, bool $explicit = FALSE): string
    {
        $preferred = FALSE;
        $ceiling = 0;

        foreach ($types as $type) {
            $quality = $this->acceptsAtQuality($type, $explicit);

            if ($quality > $ceiling) {
                $preferred = $type;
                $ceiling = $quality;
            }
        }

        return $preferred;
    }

    /**
     * Returns the accept quality of a submitted mime type based on the
     * request `Accept:` header. If the `$explicit` argument is `TRUE`,
     * only precise matches will be returned, excluding all wildcard (`*`)
     * directives.
     *
     * @param string $type
     * @param boolean $explicit explicit check, excludes `*`
     *
     * @return  mixed
     *
     * @throws Exception
     */
    public function acceptsAtQuality(string $type, bool $explicit = FALSE)
    {
        // Parse Accept header if required
        if ($this->_accept_content === NULL) {
            if ($this->offsetExists('Accept')) {
                $accept = $this->offsetGet('Accept');
            } else {
                $accept = '*/*';
            }

            $this->_accept_content = self::parseAcceptHeader($accept);
        }

        // If not a real mime, try and find it in config
        if (strpos($type, '/') === FALSE) {

            try
            {
                $mime = Core::$config->load('mimes.' . $type);
            }
            catch (\Modseven\Exception $e)
            {
                throw new Exception($e->getMessage(), null, $e->getCode(), $e);
            }

            if ($mime === NULL) {
                return false;
            }

            $quality = FALSE;

            foreach ($mime as $_type) {
                $quality_check = $this->acceptsAtQuality($_type, $explicit);
                $quality = ($quality_check > $quality) ? $quality_check : $quality;
            }

            return $quality;
        }

        $parts = explode('/', $type, 2);

        if (isset($this->_accept_content[$parts[0]][$parts[1]])) {
            return $this->_accept_content[$parts[0]][$parts[1]];
        }
        if ($explicit === TRUE) {
            return FALSE;
        }
        if (isset($this->_accept_content[$parts[0]]['*'])) {
            return $this->_accept_content[$parts[0]]['*'];
        }

        return $this->_accept_content['*']['*'] ?? false;
    }

    /**
     * Parses the accept header to provide the correct quality values
     * for each supplied accept type.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.1
     * @param string $accepts accept content header string to parse
     * @return  array
     */
    public static function parseAcceptHeader(?string $accepts = NULL): array
    {
        $accepts = explode(',', (string)$accepts);

        // If there is no accept, lets accept everything
        if ($accepts === NULL) {
            return ['*' => ['*' => (float)static::DEFAULT_QUALITY]];
        }

        // Parse the accept header qualities
        $accepts = self::acceptQuality($accepts);

        $parsed_accept = [];

        // This method of iteration uses less resource
        foreach (array_keys($accepts) as $key) {
            // Extract the parts
            $parts = explode('/', $key, 2);

            // Invalid content type- bail
            if (!isset($parts[1])) {
                continue;
            }

            // Set the parsed output
            $parsed_accept[$parts[0]][$parts[1]] = $accepts[$key];
        }

        return $parsed_accept;
    }

    /**
     * Parses an Accept(-*) header and detects the quality
     *
     * @param array $parts accept header parts
     * @return  array
     */
    public static function acceptQuality(array $parts): array
    {
        $parsed = [];

        // Resource light iteration
        foreach (array_keys($parts) as $key) {
            $value = trim(str_replace(["\r", "\n"], '', $parts[$key]));

            $pattern = '~\b(\;\s*+)?q\s*+=\s*+([.0-9]+)~';

            // If there is no quality directive, return default
            if (!preg_match($pattern, $value, $quality)) {
                $parsed[$value] = (float)static::DEFAULT_QUALITY;
            } else {
                $quality = $quality[2];

                if ($quality[0] === '.') {
                    $quality = '0' . $quality;
                }

                // Remove the quality value from the string and apply quality
                $parsed[trim(preg_replace($pattern, '', $value, 1), '; ')] = (float)$quality;
            }
        }

        return $parsed;
    }

    /**
     * Returns the preferred charset from the supplied array `$charsets` based
     * on the `Accept-Charset` header directive.
     *
     * @param array $charsets charsets to test
     * @return  mixed   preferred charset or `FALSE`
     */
    public function preferredCharset(array $charsets)
    {
        $preferred = FALSE;
        $ceiling = 0;

        foreach ($charsets as $charset) {
            $quality = $this->acceptsCharsetAtQuality($charset);

            if ($quality > $ceiling) {
                $preferred = $charset;
                $ceiling = $quality;
            }
        }

        return $preferred;
    }

    /**
     * Returns the quality of the supplied `$charset` argument. This method
     * will automatically parse the `Accept-Charset` header if present and
     * return the associated resolved quality value.
     *
     * @param string $charset charset to examine
     * @return  float   the quality of the charset
     */
    public function acceptsCharsetAtQuality(string $charset): float
    {
        if ($this->_accept_charset === NULL) {
            if ($this->offsetExists('Accept-Charset')) {
                $charset_header = strtolower($this->offsetGet('Accept-Charset'));
                $this->_accept_charset = self::parseCharsetHeader($charset_header);
            } else {
                $this->_accept_charset = self::parseCharsetHeader(NULL);
            }
        }

        $charset = strtolower($charset);

        if (isset($this->_accept_charset[$charset])) {
            return $this->_accept_charset[$charset];
        }
        if (isset($this->_accept_charset['*'])) {
            return $this->_accept_charset['*'];
        }
        if ($charset === 'iso-8859-1') {
            return (float)1;
        }

        return (float)0;
    }

    /**
     * Parses the `Accept-Charset:` HTTP header and returns an array containing
     * the charset and associated quality.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.2
     * @param string $charset charset string to parse
     * @return  array
     */
    public static function parseCharsetHeader(?string $charset = NULL): array
    {
        if ($charset === NULL) {
            return ['*' => (float)static::DEFAULT_QUALITY];
        }

        return self::acceptQuality(explode(',', (string)$charset));
    }

    /**
     * Returns the preferred message encoding type based on quality, and can
     * optionally ignore wildcard references. If two or more encodings have the
     * same quality, the first listed in `$encodings` will be returned.
     *
     * @param array $encodings encodings to test against
     * @param boolean $explicit explicit check, if `TRUE` wildcards are excluded
     * @return  mixed
     */
    public function preferredEncoding(array $encodings, bool $explicit = FALSE)
    {
        $ceiling = 0;
        $preferred = FALSE;

        foreach ($encodings as $encoding) {
            $quality = $this->acceptsEncodingAtQuality($encoding, $explicit);

            if ($quality > $ceiling) {
                $ceiling = $quality;
                $preferred = $encoding;
            }
        }

        return $preferred;
    }

    /**
     * Returns the quality of the `$encoding` type passed to it. Encoding
     * is usually compression such as `gzip`, but could be some other
     * message encoding algorithm. This method allows explicit checks to be
     * done ignoring wildcards.
     *
     * @param string $encoding encoding type to interrogate
     * @param boolean $explicit explicit check, ignoring wildcards and `identity`
     * @return  float
     */
    public function acceptsEncodingAtQuality(string $encoding, bool $explicit = FALSE): float
    {
        if ($this->_accept_encoding === NULL) {
            if ($this->offsetExists('Accept-Encoding')) {
                $encoding_header = $this->offsetGet('Accept-Encoding');
            } else {
                $encoding_header = NULL;
            }

            $this->_accept_encoding = self::parseEncodingHeader($encoding_header);
        }

        // Normalize the encoding
        $encoding = strtolower($encoding);

        if (isset($this->_accept_encoding[$encoding])) {
            return $this->_accept_encoding[$encoding];
        }

        if ($explicit === FALSE) {
            if (isset($this->_accept_encoding['*'])) {
                return $this->_accept_encoding['*'];
            }
            if ($encoding === 'identity') {
                return (float)static::DEFAULT_QUALITY;
            }
        }

        return (float)0;
    }

    /**
     * Parses the `Accept-Encoding:` HTTP header and returns an array containing
     * the charsets and associated quality.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.3
     * @param string $encoding charset string to parse
     * @return  array
     */
    public static function parseEncodingHeader(?string $encoding = NULL): array
    {
        // Accept everything
        if ($encoding === NULL) {
            return ['*' => (float)static::DEFAULT_QUALITY];
        }
        if ($encoding === '') {
            return ['identity' => (float)static::DEFAULT_QUALITY];
        }
        return self::acceptQuality(explode(',', (string)$encoding));
    }

    /**
     * Returns the preferred language from the supplied array `$languages` based
     * on the `Accept-Language` header directive.
     *
     * @param array $languages
     * @param boolean $explicit
     * @return  mixed
     */
    public function preferredLanguage(array $languages, bool $explicit = FALSE)
    {
        $ceiling = 0;
        $preferred = FALSE;
        $languages = $this->_orderLanguagesAsReceived($languages, $explicit);

        foreach ($languages as $language) {
            $quality = $this->acceptsLanguageAtQuality($language, $explicit);

            if ($quality > $ceiling) {
                $ceiling = $quality;
                $preferred = $language;
            }
        }

        return $preferred;
    }

    /**
     * Returns the reordered list of supplied `$languages` using the order
     * from the `Accept-Language:` HTTP header.
     *
     * @param array $languages languages to order
     * @param boolean $explicit
     * @return  array
     */
    protected function _orderLanguagesAsReceived(array $languages, bool $explicit = FALSE): array
    {
        if ($this->_accept_language_list === NULL) {
            if ($this->offsetExists('Accept-Language')) {
                $language_header = strtolower($this->offsetGet('Accept-Language'));
            } else {
                $language_header = NULL;
            }

            $this->_accept_language_list = self::_parseLanguageHeaderAsList($language_header);
        }

        $new_order = [];

        foreach ($this->_accept_language_list as $accept_language) {
            foreach ($languages as $key => $language) {
                if (($explicit && $accept_language === $language) ||
                    (!$explicit && strpos($accept_language, substr($language, 0, 2)) === 0)) {
                    $new_order[] = $language;

                    unset($languages[$key]);
                }
            }
        }

        foreach ($languages as $language) {
            $new_order[] = $language;
        }

        return $new_order;
    }

    /**
     * Parses the `Accept-Language:` HTTP header and returns an array containing
     * the language names.
     *
     * @param string $language charset string to parse
     * @return  array
     */
    protected static function _parseLanguageHeaderAsList(?string $language = NULL): array
    {
        $languages = [];
        $language = explode(',', strtolower($language));

        foreach ($language as $lang) {
            $matches = [];

            if (preg_match('/([\w-]+)\s*(;.*q.*)?/', $lang, $matches)) {
                $languages[] = $matches[1];
            }
        }

        return $languages;
    }

    /**
     * Returns the quality of `$language` supplied, optionally ignoring
     * wildcards if `$explicit` is set to a non-`FALSE` value. If the quality
     * is not found, `0.0` is returned.
     *
     * @param string $language language to interrogate
     * @param boolean $explicit explicit interrogation, `TRUE` ignores wildcards
     * @return  float
     */
    public function acceptsLanguageAtQuality(string $language, bool $explicit = FALSE): float
    {
        if ($this->_accept_language === NULL) {
            if ($this->offsetExists('Accept-Language')) {
                $language_header = strtolower($this->offsetGet('Accept-Language'));
            } else {
                $language_header = NULL;
            }

            $this->_accept_language = self::parseLanguageHeader($language_header);
        }

        // Normalize the language
        $language_parts = explode('-', strtolower($language), 2);

        if (isset($this->_accept_language[$language_parts[0]])) {
            if (isset($language_parts[1])) {
                if (isset($this->_accept_language[$language_parts[0]][$language_parts[1]])) {
                    return $this->_accept_language[$language_parts[0]][$language_parts[1]];
                }
                if ($explicit === FALSE && isset($this->_accept_language[$language_parts[0]]['*'])) {
                    return $this->_accept_language[$language_parts[0]]['*'];
                }
            } elseif (isset($this->_accept_language[$language_parts[0]]['*'])) {
                return $this->_accept_language[$language_parts[0]]['*'];
            }
        }

        if ($explicit === FALSE && isset($this->_accept_language['*'])) {
            return $this->_accept_language['*'];
        }

        return (float)0;
    }

    /**
     * Parses the `Accept-Language:` HTTP header and returns an array containing
     * the languages and associated quality.
     *
     * @link    http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html#sec14.4
     * @param string $language charset string to parse
     * @return  array
     */
    public static function parseLanguageHeader(?string $language = NULL): array
    {
        if ($language === NULL) {
            return ['*' => ['*' => (float)static::DEFAULT_QUALITY]];
        }

        $language = self::acceptQuality(explode(',', (string)$language));

        $parsed_language = [];

        foreach (array_keys($language) as $key) {
            // Extract the parts
            $parts = explode('-', $key, 2);

            // Invalid content type- bail
            if (!isset($parts[1])) {
                $parsed_language[$parts[0]]['*'] = $language[$key];
            } else {
                // Set the parsed output
                $parsed_language[$parts[0]][$parts[1]] = $language[$key];
            }
        }

        return $parsed_language;
    }

    /**
     * Sends headers to the php processor, or supplied `$callback` argument.
     * This method formats the headers correctly for output, re-instating their
     * capitalization for transmission.
     *
     * [!!] if you supply a custom header handler via `$callback`, it is
     *  recommended that `$response` is returned
     *
     * @param Response $response header to send
     * @param boolean $replace replace existing value
     * @param callback $callback optional callback to replace PHP header function
     *
     * @return  mixed
     *
     * @throws \Modseven\Exception
     */
    public function sendHeaders(Response $response, bool $replace = FALSE, $callback = NULL)
    {
        $protocol = $response->protocol();
        $status = $response->status();

        // Create the response header
        $processed_headers = [$protocol . ' ' . $status . ' ' . Response::$messages[$status]];

        // Get the headers array
        $headers = $response->headers()->getArrayCopy();

        foreach ($headers as $header => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            $processed_headers[] = Text::ucfirst($header) . ': ' . $value;
        }

        if (!isset($headers['content-type'])) {
            $processed_headers[] = 'Content-Type: ' . Core::$content_type . '; charset=' . Core::$charset;
        }

        if (Core::$expose && !isset($headers['x-powered-by'])) {
            $processed_headers[] = 'X-Powered-By: ' . Core::version();
        }

        // Get the cookies and apply
        if ($cookies = $response->cookie()) {
            $processed_headers['Set-Cookie'] = $cookies;
        }

        if (is_callable($callback)) {
            // Use the callback method to set header
            return $callback($response, $processed_headers, $replace);
        }

        $this->_sendHeadersToPhp($processed_headers, $replace);
        return $response;
    }

    /**
     * Sends the supplied headers to the PHP output buffer. If cookies
     * are included in the message they will be handled appropriately.
     *
     * @param array $headers headers to send to php
     * @param boolean $replace replace existing headers
     *
     * @return  self
     *
     * @throws \Modseven\Exception
     */
    protected function _sendHeadersToPhp(array $headers, bool $replace): self
    {
        // If the headers have been sent, get out
        if (headers_sent()) {
            return $this;
        }

        foreach ($headers as $key => $line) {
            if ($key === 'Set-Cookie' && is_array($line)) {
                // Send cookies
                foreach ($line as $name => $value) {
                    Cookie::set($name, $value['value'], $value['expiration']);
                }

                continue;
            }

            header($line, $replace);
        }

        return $this;
    }

}
